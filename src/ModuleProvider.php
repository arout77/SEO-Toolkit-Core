<?php

namespace Arout\SeoToolkit;

use Rhapsody\Core\Modules\Contracts\ModuleServiceProviderInterface;
use Rhapsody\Core\Modules\ModuleContext;
use Rhapsody\Core\Response;

class ModuleProvider implements ModuleServiceProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $context->routes()->root('GET', '/sitemap.xml', fn () => $this->sitemapResponse($context));
        $context->routes()->root('GET', '/robots.txt', fn () => $this->robotsResponse($context));
    }

    public function install(ModuleContext $context): void
    {
        // Seed defaults so settings.json exists with sensible values even
        // before an admin ever visits a (future) settings screen.
        $context->settings()->set('excluded_prefixes', '/auth,/login,/forgot-password,/reset-password,/payment');
        $context->settings()->set('excluded_controllers', 'Rhapsody\\Core\\Controllers\\DocsController');
        $context->settings()->set('excluded_middleware', 'auth');
        $context->settings()->set('change_frequency', 'weekly');
        $context->settings()->set('robots_disallow', '/auth,/login');
    }

    public function uninstall(ModuleContext $context): void
    {
        // Nothing on disk/in the DB to clean up — settings.json is left in
        // place harmlessly, same as core leaves it for any deactivated module.
    }

    private function sitemapResponse(ModuleContext $context): Response
    {
        $excludedPrefixes    = $this->csv($context->settings()->get('excluded_prefixes', ''));
        $excludedControllers = $this->csv($context->settings()->get('excluded_controllers', ''));
        $excludedMiddleware  = $this->csv($context->settings()->get('excluded_middleware', 'auth'));
        $changeFrequency     = $context->settings()->get('change_frequency', 'weekly');

        $urls = [];
        foreach ($context->routes()->registered() as $route) {
            if ($route->method !== 'GET') {
                continue;
            }
            if ($route->hasParams()) {
                continue; // can't enumerate a concrete URL for e.g. /docs/{slug}
            }
            if (array_intersect($route->middleware, $excludedMiddleware)) {
                continue;
            }
            if ($this->startsWithAny($route->path, $excludedPrefixes)) {
                continue;
            }
            if ($route->controller && in_array($route->controller, $excludedControllers, true)) {
                continue;
            }

            $urls[$route->path] = true; // dedupe
        }

        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        $today   = date('Y-m-d');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (array_keys($urls) as $path) {
            $loc = htmlspecialchars($baseUrl . $path, ENT_XML1 | ENT_QUOTES);
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$loc}</loc>\n";
            $xml .= "    <lastmod>{$today}</lastmod>\n";
            $xml .= "    <changefreq>{$changeFrequency}</changefreq>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        $response = new Response();
        $response->setHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->setContent($xml);
        return $response;
    }

    private function robotsResponse(ModuleContext $context): Response
    {
        $disallow = $this->csv($context->settings()->get('robots_disallow', ''));
        $baseUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');

        $lines = ['User-agent: *'];
        foreach ($disallow as $path) {
            $lines[] = "Disallow: {$path}";
        }
        $lines[] = '';
        $lines[] = "Sitemap: {$baseUrl}/sitemap.xml";

        $response = new Response();
        $response->setHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->setContent(implode("\n", $lines));
        return $response;
    }

    /**
     * @return array<int, string>
     */
    private function csv(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * @param array<int, string> $prefixes
     */
    private function startsWithAny(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
