<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Templates;

use BsFabric\Templates\TemplateCodeSnippets;
use BsFabric\Tests\Unit\TestCase;

final class TemplateCodeSnippetsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\Functions\when('__')->returnArg(1);
    }

    public function testBuildsSnippetsWithAssignedFieldNames(): void
    {
        $examples = TemplateCodeSnippets::build('ansprechpartner', 'Ansprechpartner', [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'bild', 'label' => 'Bild', 'type' => 'image'],
            ['name' => 'kontakte', 'label' => 'Kontakte', 'type' => 'relation'],
        ]);

        $this->assertNotSame([], $examples);

        $combined = implode("\n", array_column($examples, 'code'));

        $this->assertStringContainsString("bs_fabric('ansprechpartner')", $combined);
        $this->assertStringContainsString("->where('name', 'Müller')", $combined);
        $this->assertStringContainsString("->with('kontakte')", $combined);
        $this->assertStringContainsString("->render('card'", $combined);
        $this->assertStringContainsString("->render('detail')", $combined);
        $this->assertStringContainsString('getFields()', $combined);
        $this->assertStringContainsString("bs_fabric('ansprechpartner')", $combined);
        $this->assertStringContainsString("class=\"my-ansprechpartner\"", $combined);
        $this->assertStringContainsString('<img src="%1$s" alt="%2$s" />', $combined);
    }

    public function testEachExampleHasIntro(): void
    {
        $examples = TemplateCodeSnippets::build('article', 'Article', [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
        ]);

        foreach ($examples as $example) {
            $this->assertNotSame('', trim($example['intro']));
            $this->assertNotSame('', trim($example['label']));
            $this->assertNotSame('', trim($example['code']));
        }
    }
}
