<?php
/**
 * Email Footer Template
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
            </div><!-- .email-body -->
            <div class="email-footer" style="padding: 20px 40px; text-align: center; font-size: 14px; color: #999999; border-top: 1px solid #e8e8e8;">
                <p style="margin: 0 0 10px 0;">
                    <?php 
                    printf(
                        esc_html__( 'This email was sent from %s', 'smart-cycle-discounts' ),
                        '<a href="' . esc_url( home_url() ) . '" style="color: #999999;">' . esc_html( get_bloginfo( 'name' ) ) . '</a>'
                    );
                    ?>
                </p>
                <p style="margin: 0;">
                    <?php echo esc_html( get_bloginfo( 'description' ) ); ?>
                </p>
            </div>
        </div><!-- .email-container -->
    </div><!-- .email-wrapper -->
</body>
</html>