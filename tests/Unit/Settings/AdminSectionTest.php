<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Settings;

use OCA\AppDrop\Settings\AdminSection;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdminSectionTest extends TestCase {
	private IL10N&MockObject $l10n;
	private IURLGenerator&MockObject $urlGenerator;
	private AdminSection $section;

	protected function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->section = new AdminSection($this->l10n, $this->urlGenerator);
	}

	public function testGetId(): void {
		$this->assertSame('appdrop', $this->section->getID());
	}

	public function testGetName(): void {
		$this->assertSame('AppDrop', $this->section->getName());
	}

	public function testGetPriority(): void {
		$this->assertSame(90, $this->section->getPriority());
	}

	public function testGetIcon(): void {
		$this->urlGenerator->method('imagePath')
			->with('appdrop', 'app-dark.svg')
			->willReturn('/apps/appdrop/img/app-dark.svg');

		$this->assertSame('/apps/appdrop/img/app-dark.svg', $this->section->getIcon());
	}
}
