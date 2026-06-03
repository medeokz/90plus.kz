<?php

declare(strict_types=1);

/**
 * Закрыть HTTP-ответ до тяжёлой работы (против nginx 504).
 */
return function (string $message): void {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    if (! headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Connection: close');
        header('Content-Length: '.strlen($message));
    }

    echo $message;

    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    } else {
        @ob_end_flush();
        @flush();
    }

    @ini_set('max_execution_time', '0');
    @set_time_limit(0);
    ignore_user_abort(true);
};
