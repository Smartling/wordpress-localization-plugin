<?php

namespace Smartling\DbAl\WordpressContentEntities;

interface EntityWithPostStatus extends EntityInterface {
    public function translationCompleted(): void;

    public function translationDrafted(): void;
}
