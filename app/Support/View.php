<?php
declare(strict_types=1);

namespace App\Support;

final class View
{
    public static function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        $basePath = App::basePath('app/Views');
        $file = $basePath . '/' . $view . '.php';

        if (!is_file($file)) {
            http_response_code(500);
            echo "View nicht gefunden: " . htmlspecialchars($view);
            return;
        }

        require $basePath . '/partials/header.php';
        require $file;
        require $basePath . '/partials/footer.php';
    }
}
