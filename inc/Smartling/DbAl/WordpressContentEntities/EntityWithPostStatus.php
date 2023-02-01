<?php

namespace Smartling\DbAl\WordpressContentEntities;

interface EntityWithPostStatus extends Entity {
    public function translationCompleted(): void;

    public function translationDrafted(): void;
}
