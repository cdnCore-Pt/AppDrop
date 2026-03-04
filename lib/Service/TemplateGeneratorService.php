<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Service;

use OCP\ITempManager;

/**
 * Generates a Nextcloud app skeleton as a .zip download.
 */
class TemplateGeneratorService
{
    public function __construct(
        private readonly ITempManager $tempManager,
    ) {
    }

    /**
     * Generate an app skeleton zip.
     *
     * @param string $appId     App ID (snake_case)
     * @param string $appName   Display name
     * @param string $namespace PHP namespace (PascalCase)
     * @param string $version   Version string
     * @param string $author    Author name
     * @param string $description App description
     * @return string Path to generated zip file
     */
    public function generate(
        string $appId,
        string $appName,
        string $namespace,
        string $version = '1.0.0',
        string $author = '',
        string $description = '',
    ): string {
        $tmpPath = $this->tempManager->getTemporaryFile('.zip');
        if ($tmpPath === false || $tmpPath === null) {
            throw new AppInstallException('Could not create temporary file for zip.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new AppInstallException('Could not create zip archive.');
        }

        $year = date('Y');
        $authorXml = $author !== '' ? $author : 'Your Name';
        $descriptionXml = $description !== '' ? htmlspecialchars($description, ENT_XML1) : "A custom Nextcloud app.";

        // appinfo/info.xml
        $zip->addFromString($appId . '/appinfo/info.xml', sprintf(
            '<?xml version="1.0"?>
<info xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="https://apps.nextcloud.com/schema/apps/info.xsd">
    <id>%1$s</id>
    <name>%2$s</name>
    <summary>%3$s</summary>
    <description><![CDATA[%3$s]]></description>
    <version>%4$s</version>
    <licence>agpl</licence>
    <author>%5$s</author>
    <namespace>%6$s</namespace>
    <category>tools</category>
    <dependencies>
        <nextcloud min-version="30" max-version="32"/>
        <php min-version="8.1"/>
    </dependencies>
    <navigations>
        <navigation>
            <name>%2$s</name>
            <route>%1$s.page.index</route>
            <order>100</order>
        </navigation>
    </navigations>
</info>
',
            $appId,
            htmlspecialchars($appName, ENT_XML1),
            $descriptionXml,
            htmlspecialchars($version, ENT_XML1),
            htmlspecialchars($authorXml, ENT_XML1),
            htmlspecialchars($namespace, ENT_XML1),
        ));

        // appinfo/routes.php
        $zip->addFromString($appId . '/appinfo/routes.php', sprintf(
            '<?php

declare(strict_types=1);

return [
    \'routes\' => [
        [\'name\' => \'page#index\', \'url\' => \'/\', \'verb\' => \'GET\'],
    ],
];
',
        ));

        // lib/AppInfo/Application.php
        $zip->addFromString($appId . '/lib/AppInfo/Application.php', sprintf(
            '<?php

declare(strict_types=1);

namespace OCA\%1$s\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap
{
    public const APP_ID = \'%2$s\';

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void
    {
    }

    public function boot(IBootContext $context): void
    {
    }
}
',
            $namespace,
            $appId,
        ));

        // lib/Controller/PageController.php
        $zip->addFromString($appId . '/lib/Controller/PageController.php', sprintf(
            '<?php

declare(strict_types=1);

namespace OCA\%1$s\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

class PageController extends Controller
{
    public function __construct(string $appName, IRequest $request)
    {
        parent::__construct($appName, $request);
    }

    /**
     * @NoCSRFRequired
     * @NoAdminRequired
     */
    public function index(): TemplateResponse
    {
        return new TemplateResponse(\'%2$s\', \'main\');
    }
}
',
            $namespace,
            $appId,
        ));

        // templates/main.php
        $zip->addFromString($appId . '/templates/main.php', sprintf(
            '<?php

declare(strict_types=1);

/** @var \OCP\IL10N $l */

\OCP\Util::addStyle(\'%1$s\', \'style\');
\OCP\Util::addScript(\'%1$s\', \'script\');
?>

<div id="%1$s">
    <h2><?php p($l->t(\'%2$s\')); ?></h2>
    <p><?php p($l->t(\'Welcome to your new app!\')); ?></p>
</div>
',
            $appId,
            $appName,
        ));

        // css/style.css
        $zip->addFromString($appId . '/css/style.css', sprintf(
            '/**
 * %s — Styles
 */

#%s {
    max-width: 800px;
    margin: 32px auto;
    padding: 0 16px;
}
',
            $appName,
            $appId,
        ));

        // js/script.js
        $zip->addFromString($appId . '/js/script.js', sprintf(
            '/**
 * %1$s — Client-side logic
 */

(function () {
    \'use strict\';
    console.log(\'%1$s loaded\');
}());
',
            $appId,
        ));

        // img/app.svg (simple icon)
        $zip->addFromString($appId . '/img/app.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
            . '<rect width="32" height="32" rx="6" fill="#0082c9"/>'
            . '<text x="16" y="22" text-anchor="middle" fill="white" font-size="18" font-family="sans-serif">'
            . strtoupper(substr($appId, 0, 1))
            . '</text></svg>',
        );

        $zip->close();

        return $tmpPath;
    }
}
