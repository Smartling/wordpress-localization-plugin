<?php

namespace Smartling\Helpers;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;

class UserHelper {
    use LoggerSafeTrait;

    public function __construct(
        private SmartlingToCMSDatabaseAccessWrapperInterface $db,
        private WordpressFunctionProxyHelper $wp,
    ) {
    }

    public function asAdministrator(callable $function): mixed
    {
        $administratorId = $this->getAdministratorId();
        $originalUserId = $this->wp->get_current_user_id();

        if ($administratorId === null) {
            $this->getLogger()->notice("Unable to get administrator for blogId=" . $this->wp->get_current_blog_id() . ', running as userId=' . $originalUserId);
            return $function();
        }

        try {
            return $function();
        } finally {
            $this->wp->wp_set_current_user($originalUserId);
        }
    }

    public function getAdministratorId(): ?int
    {
        foreach ($this->db->fetch("select user_id, meta_value from {$this->db->getPrefix()}usermeta where meta_key='{$this->db->getPrefix()}capabilities'", ARRAY_A) as $row) {
            try {
                $array = json_decode($row['meta_value'], true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                continue;
            }
            if ($array['administrator'] === true) {
                return (int)$row['user_id'];
            }
        }

        return null;
    }
}
