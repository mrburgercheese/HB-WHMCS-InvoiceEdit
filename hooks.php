<?php
/**
 * WHMCS Addon Module: HB Invoice Edit Hooks
 * 
 * @author HB Dev Team
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

// 1. Add shortcut button to Invoice Edit Page
add_hook('AdminAreaHeaderOutput', 1, function ($vars) {
    $out = '';
    if (!empty($vars['filename']) && $vars['filename'] === 'invoices' && isset($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['id'])) {
        $invId = (int) $_GET['id'];
        $out .= '<script>
        $(document).ready(function() {
            var btnHtml = \'<a href="addonmodules.php?module=hb_invoice_editor&inv_id=' . $invId . '" class="btn btn-warning btn-sm" style="margin-left: 10px; font-weight: 600;"><i class="fas fa-edit"></i> Edit Invoice (HB)</a>\';
            $(".header-lined h1").append(" " + btnHtml);
        });
        </script>';
    }

    // 2. Ensure Lara Theme Sidebar menu item is active (highlighted bright white)
    $out .= '<script>
    $(document).ready(function() {
        if (window.location.href.indexOf("module=hb_invoice_editor") !== -1) {
            $(".sidebar-menu a").each(function() {
                var h = $(this).attr("href") || "";
                if (h.indexOf("module=hb_invoice_editor") !== -1) {
                    $(this).parents("li").addClass("active");
                    $(this).css({"color": "#ffffff", "font-weight": "bold"});
                }
            });
        }
    });
    </script>';

    return $out;
});
