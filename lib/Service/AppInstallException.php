<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Service;

/**
 * Any user-facing failure while installing, updating, restoring or removing an app.
 *
 * Own file so PSR-4 autoloading finds it from every service that throws it,
 * not only after AppInstallService happened to be loaded first.
 */
class AppInstallException extends \RuntimeException {
}
