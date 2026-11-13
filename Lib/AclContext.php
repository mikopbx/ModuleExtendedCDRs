<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2024 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace Modules\ModuleExtendedCDRs\Lib;

use MikoPBX\AdminCabinet\Controllers\SessionController;
use MikoPBX\Common\Providers\SessionProvider;
use MikoPBX\Core\System\Util;

/**
 * Builds the session context passed to the APPLY_ACL_FILTERS_TO_CDR_QUERY hook.
 *
 * Since ModuleUsersUI commit 02fa6fb the hook reads the user role only from the
 * context argument (JWT for REST). In the admin web panel the role lives in the
 * PHP session, so without passing it the ACL filter is silently skipped and a
 * restricted user sees all CDR records. This helper recovers the web-session role
 * and shapes it like the REST context so the hook applies the same filtering.
 */
class AclContext
{
    /**
     * Build the ACL context from the current admin web session.
     *
     * Returns an empty array for the legitimate "no restriction" cases (CLI
     * workers, no session service, or an authenticated user without a
     * ModuleUsersUI role). If resolving the role of an authenticated user fails
     * unexpectedly, it throws instead of returning [] — so a broken permission
     * lookup denies the report rather than silently exposing the full CDR set.
     *
     * @return array Context with the 'role' key, or [] when no restriction applies.
     * @throws \RuntimeException When the ACL context cannot be resolved.
     */
    public static function fromWebSession(): array
    {
        if (php_sapi_name() === 'cli') {
            return [];
        }
        try {
            $di = MikoPBXVersion::getDefaultDi();
            if ($di === null || !$di->has(SessionProvider::SERVICE_NAME)) {
                // No session context at all (e.g. non-web entry point): legitimately
                // unrestricted, same as an install without ModuleUsersUI.
                return [];
            }
            $session = $di->get(SessionProvider::SERVICE_NAME)->get(SessionController::SESSION_ID);
            if (!is_array($session) || empty($session[SessionController::ROLE])) {
                // Authenticated but not a role-restricted ModuleUsersUI user.
                return [];
            }
            return [
                'auth_type' => 'session',
                'user_name' => $session[SessionController::USER_NAME] ?? null,
                'role'      => $session[SessionController::ROLE],
            ];
        } catch (\Throwable $e) {
            // Fail CLOSED: an unexpected error while resolving an authenticated user's
            // role must not silently drop the ACL filter and expose the full CDR set.
            // The legitimate "no restriction" cases return [] above without throwing.
            Util::sysLogMsg(self::class, 'Failed to resolve ACL context, denying report: ' . get_class($e) . ': ' . $e->getMessage());
            throw new \RuntimeException('acl_context_unavailable', 0, $e);
        }
    }
}
