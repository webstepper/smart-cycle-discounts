<?php
/**
 * Email Header Template
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/templates/emails/partials
 * @since      1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
    <style type="text/css">
        body {
            margin: 0;
            padding: 0;
            background-color: #f7f7f7;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
        }
        
        .email-wrapper {
            width: 100%;
            background-color: #f7f7f7;
            padding: 20px 0;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .email-header {
            padding: 30px 40px;
            text-align: center;
            border-bottom: 1px solid #e8e8e8;
        }
        
        .email-header h1 {
            margin: 0;
            font-size: 24px;
            color: #333333;
        }
        
        .email-body {
            padding: 40px;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <div class="email-header">
                <h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
            </div>
            <div class="email-body">