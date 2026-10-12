<?php

use PHPUnit\Framework\TestCase;

class CdekDispatchTemplateTest extends TestCase
{
    public function testWarningListRendersActualMessages()
    {
        $html = $this->render(array('СДЭК ещё формирует квитанцию.', 'Ошибка <test>'));
        $this->assertStringContainsString('СДЭК ещё формирует квитанцию.', $html);
        $this->assertStringContainsString('Ошибка &lt;test&gt;', $html);
        $this->assertStringNotContainsString('> Array', $html);
    }

    public function testStringWarningStillRenders()
    {
        $this->assertStringContainsString('CDEK: rejected', $this->render('CDEK: rejected'));
    }

    private function render($warning)
    {
        $root = dirname(DIR_SYSTEM);
        $loader = new \Twig\Loader\FilesystemLoader($root . '/admin/view/template');
        $twig = new \Twig\Environment($loader, array('cache' => false, 'autoescape' => false));
        return $twig->render('extension/module/cdek_integrator/dispatch_info_full.twig', array(
            'error_warning' => $warning
        ));
    }
}
