<?php

namespace Espo\Modules\GoogleIntegration\EntryPoints;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\EntryPoint\EntryPoint;
use Espo\Core\EntryPoint\Traits\NoAuth;

/**
 * OAuth callback: deliver authorization code to opener via postMessage (COOP-safe).
 */
class OauthCallback implements EntryPoint
{
    use NoAuth;

    public function run(Request $request, Response $response): void
    {
        $response->setHeader('Content-Type', 'text/html; charset=UTF-8');

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>OAuth</title></head><body>'
            . '<p style="font-family:sans-serif;font-size:14px;">'
            . 'Connecting Google account… This window should close automatically.</p>'
            . '<script>'
            . '(function () {'
            . 'var params = new URLSearchParams(window.location.search);'
            . 'var payload = {'
            . 'type: "googleIntegrationOAuthCallback",'
            . 'code: params.get("code"),'
            . 'error: params.get("error")'
            . '};'
            . 'if (window.opener && !window.opener.closed) {'
            . 'try { window.opener.postMessage(payload, window.location.origin); } catch (e) {}'
            . '}'
            . 'if (payload.code || payload.error) {'
            . 'window.setTimeout(function () { window.close(); }, 400);'
            . '}'
            . '})();'
            . '</script></body></html>';
    }
}
