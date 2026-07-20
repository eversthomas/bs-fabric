<?php

declare(strict_types=1);

namespace BsFabric\Admin\Components;

use BsFabric\Geo\AddressGeocodingService;

final class SchemaNotices
{
    public static function enqueueToastScript(): void
    {
        $messages = [];

        if (isset($_GET['bs_fabric_notice'], $_GET['bs_fabric_message'])) {
            $message = sanitize_text_field(rawurldecode((string) wp_unslash($_GET['bs_fabric_message'])));

            if ($message !== '') {
                $messages[] = $message;
            }
        }

        $geocodeNotice = AddressGeocodingService::consumeAdminNotice();

        if ($geocodeNotice !== null) {
            $messages[] = $geocodeNotice;
        }

        if ($messages === []) {
            return;
        }

        wp_add_inline_script(
            'bs-fabric-bsf-ui',
            sprintf(
                'document.addEventListener("DOMContentLoaded",function(){if(window.bsFabricUi){%s}});',
                implode('', array_map(
                    static fn (string $msg): string => sprintf(
                        'window.bsFabricUi.toast(%s);',
                        wp_json_encode($msg)
                    ),
                    $messages
                ))
            )
        );
    }
}
