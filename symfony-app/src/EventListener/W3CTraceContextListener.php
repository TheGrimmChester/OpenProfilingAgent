<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * W3C Trace Context Listener
 * 
 * Reads W3C Trace Context headers (traceparent, tracestate) from the incoming HTTP request
 * and passes them to the OPA extension via opa_set_w3c_context().
 * 
 * This is necessary because PHP-FPM doesn't populate $_SERVER with custom FastCGI parameters,
 * so the extension cannot access these headers directly. Symfony's Request object has access
 * to all headers, so we use it as a bridge.
 */
class W3CTraceContextListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // Use REQUEST event with high priority to run early, before controllers
            KernelEvents::REQUEST => ['onKernelRequest', 1000],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // CRITICAL: Write to a file we can check (use /var/www/symfony/var/log which is writable)
        $logFile = '/var/www/symfony/var/log/w3c_listener.log';
        @file_put_contents($logFile, date('Y-m-d H:i:s') . " - Listener called\n", FILE_APPEND);
        
        // Only process master requests (not sub-requests)
        if (!$event->isMainRequest()) {
            @file_put_contents($logFile, date('Y-m-d H:i:s') . " - Not main request, skipping\n", FILE_APPEND);
            return;
        }

        // Check if OPA extension is loaded
        if (!function_exists('opa_set_w3c_context')) {
            @file_put_contents($logFile, date('Y-m-d H:i:s') . " - Function not available\n", FILE_APPEND);
            return;
        }

        $request = $event->getRequest();
        
        // Get W3C Trace Context headers from Request object
        $traceparent = $request->headers->get('traceparent');
        $tracestate = $request->headers->get('tracestate');
        
        @file_put_contents($logFile, sprintf(
            "%s - Headers: traceparent=%s, tracestate=%s\n",
            date('Y-m-d H:i:s'),
            $traceparent ? substr($traceparent, 0, 50) : 'NULL',
            $tracestate ? substr($tracestate, 0, 50) : 'NULL'
        ), FILE_APPEND);
        
        // If traceparent is present, pass it to the extension
        if ($traceparent) {
            // Call the extension function to set W3C context
            // tracestate is optional, can be null
            $result = opa_set_w3c_context($traceparent, $tracestate);
            @file_put_contents($logFile, sprintf(
                "%s - Called opa_set_w3c_context, result=%s\n",
                date('Y-m-d H:i:s'),
                $result ? 'true' : 'false'
            ), FILE_APPEND);
        } else {
            @file_put_contents($logFile, date('Y-m-d H:i:s') . " - No traceparent header\n", FILE_APPEND);
        }
    }
}

