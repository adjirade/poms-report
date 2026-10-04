<?php

namespace Tests\Unit;

use App\Support\EnvFileEditor;
use Tests\TestCase;

class EnvFileEditorTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/poms_env_test_'.uniqid().'.env';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }

        parent::tearDown();
    }

    private function editorWith(string $content): EnvFileEditor
    {
        file_put_contents($this->path, $content);

        return new EnvFileEditor($this->path);
    }

    public function test_reads_whitelisted_values_and_strips_quotes(): void
    {
        $editor = $this->editorWith(implode("\n", [
            'APP_NAME="POMS Report"',
            'PLANT_ID=PKS_02',
            'SECRET_NOT_WHITELISTED=abc', // tidak masuk whitelist
            '# komentar',
        ]));

        $values = $editor->values();

        $this->assertSame('POMS Report', $values['APP_NAME']);
        $this->assertSame('PKS_02', $values['PLANT_ID']);
        $this->assertArrayNotHasKey('SECRET_NOT_WHITELISTED', $values);
    }

    public function test_updates_existing_and_appends_new_keys_preserving_comments(): void
    {
        $editor = $this->editorWith(implode("\n", [
            '# Header komentar',
            'APP_NAME="POMS Report"',
            '',
            'PLANT_ID=PKS_01',
        ]));

        $updated = $editor->set([
            'APP_NAME' => 'Pabrik Baru',
            'TELEGRAM_BOT_USERNAME' => 'new_bot',
        ]);

        $content = file_get_contents($this->path);

        $this->assertStringContainsString('# Header komentar', $content);
        $this->assertStringContainsString('APP_NAME="Pabrik Baru"', $content);
        $this->assertStringContainsString('TELEGRAM_BOT_USERNAME=new_bot', $content);
        $this->assertEqualsCanonicalizing(['APP_NAME', 'TELEGRAM_BOT_USERNAME'], $updated);
    }

    public function test_non_whitelisted_keys_are_ignored(): void
    {
        $editor = $this->editorWith('APP_NAME=POMS');

        $updated = $editor->set([
            'DB_PASSWORD' => 'hacked',
            'APP_URL' => 'https://example.com',
        ]);

        $content = file_get_contents($this->path);

        $this->assertStringNotContainsString('DB_PASSWORD', $content);
        $this->assertStringContainsString('APP_URL=https://example.com', $content);
        $this->assertSame(['APP_URL'], $updated);
    }

    public function test_empty_secret_does_not_overwrite_existing_value(): void
    {
        $editor = $this->editorWith('TELEGRAM_BOT_TOKEN=123456:ABC-DEF');

        $updated = $editor->set(['TELEGRAM_BOT_TOKEN' => '']); // artinya: jangan diubah

        $this->assertSame([], $updated);
        $this->assertSame('123456:ABC-DEF', (new EnvFileEditor($this->path))->values()['TELEGRAM_BOT_TOKEN']);
    }

    public function test_non_empty_secret_is_updated(): void
    {
        $editor = $this->editorWith('TELEGRAM_BOT_TOKEN=old');

        $editor->set(['TELEGRAM_BOT_TOKEN' => 'new-token']);

        $this->assertSame('new-token', (new EnvFileEditor($this->path))->values()['TELEGRAM_BOT_TOKEN']);
    }

    public function test_values_contain_spaces_are_quoted(): void
    {
        $editor = $this->editorWith('');

        $editor->set(['COMPANY_NAME' => 'PT Sawit Sejahtera']);

        $this->assertStringContainsString('COMPANY_NAME="PT Sawit Sejahtera"', file_get_contents($this->path));
        $this->assertSame('PT Sawit Sejahtera', (new EnvFileEditor($this->path))->values()['COMPANY_NAME']);
    }
}
