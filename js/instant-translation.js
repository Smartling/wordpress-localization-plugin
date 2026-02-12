/**
 * Instant Translation Module
 *
 * Handles instant translation requests with exponential backoff polling.
 * Polling intervals: 1s -> 2s -> 4s -> 8s -> 16s -> 30s (subsequent)
 * Timeout: 2 minutes
 */
(function ($) {
    'use strict';

    const InstantTranslation = {
        // Polling configuration
        POLL_INTERVALS: [1000, 2000, 4000, 8000, 16000], // ms
        MAX_BACKOFF_INTERVAL: 30000, // 30 seconds
        TIMEOUT: 120000, // 2 minutes

        // State
        isPolling: false,
        pollIntervalIndex: 0,
        startTime: null,
        pollTimeoutId: null,
        submissionIds: [],
        completedCount: 0,

        /**
         * Initialize instant translation functionality
         */
        init: function () {
            this.attachEvents();
        },

        /**
         * Attach event handlers
         */
        attachEvents: function () {
            const self = this;
            $('#instantTranslateButton').on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                self.handleInstantTranslate();
            });
        },

        /**
         * Handle instant translation button click
         */
        handleInstantTranslate: function () {
            if (this.isPolling) {
                this.showError('Translation already in progress. Please wait...');
                return;
            }

            // Validate selections
            const targetBlogIds = this.getSelectedTargetLocales();
            if (targetBlogIds.length === 0) {
                this.showError('Please select at least one target locale.');
                return;
            }

            if (targetBlogIds.length > 1) {
                this.showError('Instant translation supports one target locale at a time. Please select only one locale.');
                return;
            }

            // Get content info
            const contentType = window.currentContent?.contentType || '';
            const contentId = window.currentContent?.id?.[0] || 0;

            if (!contentType || !contentId) {
                this.showError('Unable to determine content type or ID.');
                return;
            }

            // Disable button
            this.setButtonState(true);
            this.hideError();
            this.showStatus('Preparing instant translation...');

            // Send request
            this.requestInstantTranslation(contentType, contentId, targetBlogIds[0]);
        },

        /**
         * Get selected target locales
         */
        getSelectedTargetLocales: function () {
            const blogIds = [];
            $('.job-wizard .mcheck:checkbox:checked').each(function () {
                blogIds.push($(this).attr('data-blog-id'));
            });
            return blogIds;
        },

        /**
         * Request instant translation
         */
        requestInstantTranslation: async function (contentType, contentId, targetBlogId) {
            const self = this;
            const url = ajaxurl + '?action=smartling_instant_translation';

            // Collect all items to translate (main content + related content)
            const itemsToTranslate = [{ contentType, contentId }];

            // Add selected related content
            $('.relation-checkbox:checked').each(function() {
                itemsToTranslate.push({
                    contentType: $(this).attr('data-content-type'),
                    contentId: parseInt($(this).attr('data-id'))
                });
            });

            const submissionIds = [];

            try {
                // Create instant translation requests for all items
                for (const item of itemsToTranslate) {
                    const response = await $.post(url, {
                        contentType: item.contentType,
                        contentId: item.contentId,
                        targetBlogId: targetBlogId
                    });

                    if (response.success && response.data && response.data.submissionId) {
                        submissionIds.push(response.data.submissionId);
                    } else {
                        throw new Error(response.data?.message || 'Failed to start instant translation.');
                    }
                }

                if (submissionIds.length > 0) {
                    self.submissionIds = submissionIds;
                    self.completedCount = 0;
                    self.startPolling();
                } else {
                    self.showError('No items to translate.');
                    self.setButtonState(false);
                }
            } catch (e) {
                self.showError(e.message || 'Failed to start instant translation.');
                self.setButtonState(false);
            }
        },

        /**
         * Start polling for translation status
         */
        startPolling: function () {
            this.isPolling = true;
            this.pollIntervalIndex = 0;
            this.startTime = Date.now();

            const count = this.submissionIds.length;
            this.showStatus(`Translating ${count} item${count > 1 ? 's' : ''}... This will take approximately 2 minutes.`);
            this.updateProgress(5); // Initial progress

            this.poll();
        },

        /**
         * Poll translation status
         */
        poll: async function () {
            const self = this;

            // Check timeout
            const elapsed = Date.now() - this.startTime;
            if (elapsed >= this.TIMEOUT) {
                this.stopPolling();
                this.showError('Translation request timed out after 2 minutes. Please check the submission status manually.');
                this.setButtonState(false);
                return;
            }

            // Update progress based on time elapsed
            const progressPercent = Math.min(90, (elapsed / this.TIMEOUT) * 100);
            this.updateProgress(progressPercent);

            const url = ajaxurl + '?action=smartling_instant_translation_status';
            let completedCount = 0;
            let failedCount = 0;
            let errorMessage = '';

            try {
                // Poll all submissions
                const promises = this.submissionIds.map(submissionId =>
                    $.post(url, { submissionId: submissionId })
                );

                const responses = await Promise.all(promises);

                responses.forEach(function(response) {
                    if (response.success && response.data) {
                        const status = response.data.status;

                        switch (status) {
                            case 'completed':
                                completedCount++;
                                break;

                            case 'failed':
                                failedCount++;
                                errorMessage = response.data.message || 'Translation failed.';
                                break;

                            case 'in_progress':
                            case 'pending':
                                // Still in progress
                                break;
                        }
                    }
                });

                // Update completed count
                self.completedCount = completedCount;

                // Update status message
                if (self.submissionIds.length > 1) {
                    self.showStatus(`Translating ${self.submissionIds.length} items... ${completedCount} completed.`);
                }

                // Check if all completed
                if (completedCount === self.submissionIds.length) {
                    self.stopPolling();
                    self.updateProgress(100);
                    self.showSuccess(`All ${self.submissionIds.length} item${self.submissionIds.length > 1 ? 's' : ''} translated successfully!`);
                    self.setButtonState(false);

                    setTimeout(function () {
                        window.location.reload();
                    }, 2000);
                    return;
                }

                // Check if any failed
                if (failedCount > 0 && (completedCount + failedCount) === self.submissionIds.length) {
                    self.stopPolling();
                    self.showError(`${failedCount} translation(s) failed. ${completedCount} completed successfully. ${errorMessage}`);
                    self.setButtonState(false);
                    return;
                }

            } catch (e) {
                // Continue polling on error
            }

            // Schedule next poll
            self.scheduleNextPoll();
        },

        /**
         * Schedule next poll with exponential backoff
         */
        scheduleNextPoll: function () {
            const interval = this.getNextPollInterval();
            const self = this;

            this.pollTimeoutId = setTimeout(function () {
                self.poll();
            }, interval);

            this.pollIntervalIndex++;
        },

        /**
         * Get next poll interval with exponential backoff
         */
        getNextPollInterval: function () {
            if (this.pollIntervalIndex < this.POLL_INTERVALS.length) {
                return this.POLL_INTERVALS[this.pollIntervalIndex];
            }
            // All subsequent polls use max backoff interval
            return this.MAX_BACKOFF_INTERVAL;
        },

        /**
         * Stop polling
         */
        stopPolling: function () {
            this.isPolling = false;
            if (this.pollTimeoutId) {
                clearTimeout(this.pollTimeoutId);
                this.pollTimeoutId = null;
            }
        },

        /**
         * Set button state
         */
        setButtonState: function (disabled) {
            const $button = $('#instantTranslateButton');
            if (disabled) {
                $button.prop('disabled', true).addClass('is-busy').text('Translating...');
            } else {
                $button.prop('disabled', false).removeClass('is-busy').text('Request Instant Translation');
            }
        },

        /**
         * Show status message
         */
        showStatus: function (message) {
            const $status = $('#instant-status');
            const $statusText = $('#instant-status-text');

            $statusText.html('<strong>' + message + '</strong>');
            $status.removeClass('hidden');
            $('#error-messages').html('').hide();
        },

        /**
         * Hide status
         */
        hideStatus: function () {
            $('#instant-status').addClass('hidden');
        },

        /**
         * Update progress bar
         */
        updateProgress: function (percent) {
            $('#instant-progress-bar').css('width', percent + '%');
        },

        /**
         * Show error message
         */
        showError: function (message) {
            const $errorMessages = $('#error-messages');
            $errorMessages.html('<span style="color: #d63638;">' + message + '</span>').show();
            this.hideStatus();
        },

        /**
         * Hide error message
         */
        hideError: function () {
            $('#error-messages').html('').hide();
        },

        /**
         * Show success message
         */
        showSuccess: function (message) {
            const $statusText = $('#instant-status-text');
            $statusText.html('<strong style="color: #00a32a;">' + message + '</strong>');
            this.updateProgress(100);
        }
    };

    // Initialize when document is ready
    $(document).ready(function () {
        InstantTranslation.init();
    });

})(jQuery);
