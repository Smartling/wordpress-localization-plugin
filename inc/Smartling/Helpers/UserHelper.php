<?php

namespace Smartling\Helpers;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;

class UserHelper {
    use LoggerSafeTrait;

    private const ADMINISTRATOR = 'administrator';
    private const EDITOR = 'editor';

    public function __construct(
        private SmartlingToCMSDatabaseAccessWrapperInterface $db,
        private WordpressFunctionProxyHelper $wp,
    ) {
    }

    public function asAdministratorOrEditor(callable $function): mixed
    {
        $originalUser = $this->wp->wp_get_current_user();
        if ($originalUser instanceof \WP_User && array_intersect([self::ADMINISTRATOR, self::EDITOR], $originalUser->roles)) {
            $this->getLogger()->debug("Current user userId={$originalUser->ID} is administrator or editor, no impersonation");
            return $function();
        }

        $privilegedId = $this->getAdministratorOrEditorId();
        $originalUserId = $this->wp->get_current_user_id();

        if ($privilegedId === null) {
            $this->getLogger()->warning("Unable to get administrator or editor for blogId=" . $this->wp->get_current_blog_id() . ', running as original userId=' . $originalUserId);
            return $function();
        }

        try {
            return $function();
        } finally {
            $this->wp->wp_set_current_user($originalUserId);
        }
    }

    public function getAdministratorOrEditorId(): ?int
    {
        foreach ($this->db->fetch("select user_id, meta_value from {$this->db->getBasePrefix()}usermeta where meta_key='{$this->db->getPrefix()}capabilities'", ARRAY_A) as $row) {
            try {
                $array = json_decode($row['meta_value'], true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                continue;
            }
            if (($array[self::ADMINISTRATOR] ?? false) === true || ($array[self::EDITOR] ?? false) === true) {
                return (int)$row['user_id'];
            }
        }

        return null;
    }
}
