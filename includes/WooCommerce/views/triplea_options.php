<?php
    wp_enqueue_style( 'wctriplea-admin-style' );

    $plugin_options           = 'woocommerce_' . 'triplea_payment_gateway' . '_settings';
    $plugin_settings_defaults = array();
    $plugin_settings          = get_option( $plugin_options, $plugin_settings_defaults );

    $tripleaStatuses = [
        'paid'      => 'Paid (awaiting confirmation)',
        'confirmed' => 'Paid (confirmed)',
        'invalid'   => 'Invalid',
    ];
    // There is an additional state (on hold) which is set by WooCommerce on order creation.

    $statuses = [
        'paid'      => 'wc-on-hold',
        'confirmed' => 'wc-processing',
        'invalid'   => 'wc-failed',
    ];

    $wcStatuses = wc_get_order_statuses();

    compact( 'tripleaStatuses', 'statuses', 'wcStatuses' );

    //Call data from Options
    //Account Section
    $merchantKey  = ( !empty( $plugin_settings['merchant_key'] ) ) ? $plugin_settings['merchant_key'] : '';
    $clientID     = ( !empty( $plugin_settings['client_id'] ) ) ? $plugin_settings['client_id'] : '';
    $clientSecret = ( !empty( $plugin_settings['client_secret'] ) ) ? $plugin_settings['client_secret'] : '';

    //Settings Section
    $enabled  = ( isset( $plugin_settings['enabled'] ) && $plugin_settings['enabled'] == 'yes' ) ? 'checked' : '';
    $testMode = ( isset( $plugin_settings['test_mode'] ) && $plugin_settings['test_mode'] == 'yes' ) ? 'checked' : '';
    $debugLog = ( isset( $plugin_settings['debug_log'] ) && $plugin_settings['debug_log'] == 'yes' ) ? 'checked' : '';

    //Design Section
    $cryptoText     = ( isset( $plugin_settings['crypto_text'] ) && !empty( $plugin_settings['crypto_text'] ) ) ? $plugin_settings['crypto_text'] : 'Cryptocurrency';
    $cryptoShowLogo = ( isset( $plugin_settings['crypto_logo'] ) && $plugin_settings['crypto_logo'] == 'show_logo' ) ? 'checked' : '';
    $cryptoNoLogo   = ( isset( $plugin_settings['crypto_logo'] ) && $plugin_settings['crypto_logo'] == 'no_logo' ) ? 'checked' : '';

    //oAuth Token
    $oauthToken = ( isset( $plugin_settings['oauth_token'] ) && empty( $plugin_settings['oauth_token'] ) ) ? false : true;
?>

<div class="triplea-wrapper">
    <div class="triplea-tab">
        <div class="triplea-tab-item">
            <button class="tablinks active" onclick="expandSettings(event, 'account')"><?php _e( 'Account', 'wc-triplea-crypto-payment' ); ?></button>
            <button class="tablinks" onclick="expandSettings(event, 'settings')"><?php _e( 'Settings', 'wc-triplea-crypto-payment' ); ?></button>
            <button class="tablinks" onclick="expandSettings(event, 'design')"><?php _e( 'Design', 'wc-triplea-crypto-payment' ); ?></button>
            <button class="tablinks" onclick="expandSettings(event, 'status')"><?php _e( 'Order Status', 'wc-triplea-crypto-payment' ); ?></button>
            <button class="tablinks" onclick="expandSettings(event, 'account-verification')"><?php _e( 'Account Verification', 'wc-triplea-crypto-payment' ); ?></button>
        </div>
        <div class="triplea-tab-content">
            <div id="account" class="tab-content">
                <div class="triplea-settings-notice">
                <?php
                    $noticeMessage1   = __( 'Fill in the form below with the information you received via email after creating your TripleA account.', 'wc-triplea-crypto-payment' );
                    $noticeMessage2p1 = __( 'Can\'t find it?', 'wc-triplea-crypto-payment' );
                    $noticeMessage2p2 = __( 'Click this page', 'wc-triplea-crypto-payment' );
                    $noticeMessage2p3 = __( 'for more information on where to find your Merchant Key, Client ID and Client Secret.', 'wc-triplea-crypto-payment' );
                    $noticeMessage3p1 = __( 'If you don\'t have a TripleA account yet, sign up for one', 'wc-triplea-crypto-payment' );
                    $noticeMessage3p2 = __( 'here!', 'wc-triplea-crypto-payment' );

                    echo sprintf('<p>%s</p>
                        <p>%s <a href="#" target="_blank">%s</a> %s</p>
                        <p>%s <a href="https://cutt.ly/WBnnaEI" target="_blank">%s</a></p>',
                        $noticeMessage1, $noticeMessage2p1, $noticeMessage2p2, $noticeMessage2p3, $noticeMessage3p1, $noticeMessage3p2);
                ?>
                </div>
                <div class="triplea-form-group">
                    <label for="merchantKey"><?php _e( 'Merchant Key', 'wc-triplea-crypto-payment' ); ?></label>
                    <input id="merchantKey" type="text" name="merchantKey" value="<?php echo $merchantKey; ?>">
                </div>
                <div class="triplea-form-group">
                    <label for="clientID"><?php _e( 'Client ID', 'wc-triplea-crypto-payment' ); ?></label>
                    <input id="clientID" type="text" name="clientID" value="<?php echo $clientID; ?>">
                </div>
                <div class="triplea-form-group">
                    <label for="clientSecret"><?php _e( 'Client Secret', 'wc-triplea-crypto-payment' ); ?></label>
                    <input id="clientSecret" type="text" name="clientSecret" value="<?php echo $clientSecret; ?>">
                </div>
                <input type="hidden" name="oAuthToken" id="oAuthToken">
                <input type="hidden" name="oAuthTokenExpiry" id="oAuthTokenExpiry">
            </div>
            <div id="settings" class="tab-content">
                <ol class="switches">
                    <li>
                        <input type="checkbox" id="1"<?php echo $enabled; ?>>
                        <label for="1">
                        <span>
                            <div class="checkbox-label"><?php _e( 'Enable TripleA Cryptocurrency Payments', 'wc-triplea-crypto-payment' ); ?></div>
                        </span>
                        <span></span>
                        </label>
                    </li>
                    <li>
                        <input type="checkbox" id="2"<?php echo $testMode; ?>>
                        <label for="2">
                        <span>
                            <div class="checkbox-label"><?php _e( 'Enable Test Mode', 'wc-triplea-crypto-payment' ); ?></div>
                            <div class="checkbox-instruction"><?php _e( 'Only TestBTC will be available on checkout page!', 'wc-triplea-crypto-payment' ); ?></div>
                        </span>
                        <span></span>
                        </label>
                    </li>
                    <li>
                        <input type="checkbox" id="3"<?php echo $debugLog; ?>>
                        <label for="3">
                        <span>
                            <div class="checkbox-label"><?php _e( 'Enable Debug Log', 'wc-triplea-crypto-payment' ); ?></div>
                            <div class="checkbox-instruction"><?php _e( 'Enable this option to log sensitive & important information when support is needed!', 'wc-triplea-crypto-payment' ); ?></div>
                        </span>
                        <span></span>
                        </label>
                    </li>
                </ol>
            </div>
            <div id="design" class="tab-content">
                <div class="triplea-settings-notice">
                    <?php _e( 'Customize the way your checkout page looks', 'wc-triplea-crypto-payment' ); ?>
                </div>
                <div class="triplea-form-group">
                    <label for="cryptoLogo"><?php _e( 'Select Image to load on checkout page', 'wc-triplea-crypto-payment' ); ?></label>
                    <input type="radio" name="cryptoLogo" value="show_logo" <?php echo $cryptoShowLogo; ?>> <img src="<?php echo WC_TRIPLEA_CRYPTO_PAYMENT_ASSETS . '/images/crypto-icon.png' ?>" alt="Crypto Icon Full">
                    <input type="radio" name="cryptoLogo" value="no_logo" <?php echo $cryptoNoLogo; ?>> <?php _e( 'No Logo', 'wc-triplea-crypto-payment' ); ?>
                </div>
                <div class="triplea-form-group">
                    <label for="formDescription"><?php _e( 'Add Text (Optional)', 'wc-triplea-crypto-payment' ); ?></label>
                    <input id="formDescription" type="text" name="formDescription" value="<?php echo $cryptoText; ?>">
                </div>
            </div>
            <div id="status" class="tab-content">
                <?php foreach ( $tripleaStatuses as $tripleaState => $tripleaName ) : ?>
                <div class="triplea-form-group">
                    <label for="triplea_state_<?php echo $tripleaState; ?>"><?php echo $tripleaName; ?></label>
                    <select id="triplea_state_<?php echo $tripleaState; ?>" name="triplea_woocommerce_order_states[<?php echo $tripleaState; ?>]">
                    <?php
                        $orderStates = isset( $plugin_settings['triplea_woocommerce_order_states'] ) ? $plugin_settings['triplea_woocommerce_order_states'] : array();
                        foreach ( $wcStatuses as $wcState => $wcName ) {
                            $currentOption = isset( $orderStates[ $tripleaState ] ) ? $orderStates[ $tripleaState ] : $statuses[ $tripleaState ];
                            echo "<option value='$wcState'";
                            if ( $currentOption === $wcState ) {
                                echo 'selected';
                            }
                            echo ">$wcName</option>";
                        }
                        ?>
                    </select>
                </div>
                <?php if(strpos($tripleaName, 'awaiting') !== FALSE): ?>
                <p>Payment not guaranteed yet at this stage! Do not yet provide the product or service.</p>
                <?php endif; ?>
			    <?php endforeach; ?>
            </div>
            <div id="account-verification" class="tab-content">
                <div class="info-content">
                    <p><?php _e( 'All businesses are required to go through our business verification process before accepting crypto payments.', 'wc-triplea-crypto-payment' ); ?></p>
                    <?php
                    $infoContent1 = __( 'Kindly send the documents listed', 'wc-triplea-crypto-payment' );
                    $infoContent2 = __( 'here', 'wc-triplea-crypto-payment' );
                    $infoContent3 = __( 'to', 'wc-triplea-crypto-payment' );
                    echo sprintf('<p>%s <a href="https://cutt.ly/sBmXkik" target="_blank">%s</a> %s <a href="mailto:account.verification@triple-a.io">account.verification@triple-a.io</a></p>', $infoContent1, $infoContent2, $infoContent3);
                    ?>
                </div>
            </div>
            <div class="triplea-form-group triplea-btn-wrap">
                <a href="#" id="triplea-final-step" class="triplea-btn save-btn"><?php _e( 'Save Changes', 'wc-triplea-crypto-payment' ); ?></a>
                <a href="mailto:account.verification@triple-a.io" id="triplea-verify-acnt" class="triplea-btn verify-acnt-btn"><?php _e( 'Verify Your Account', 'wc-triplea-crypto-payment' ); ?></a>
            </div>
        </div>
    </div>
</div>

<script>
    function expandSettings(evt, settingsName) {
        evt.preventDefault();

        // Declare all variables
        var i, tabcontent, tablinks;

        // Get all elements with class="tabcontent" and hide them
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }

        // Get all elements with class="tablinks" and remove the class "active"
        tablinks = document.getElementsByClassName("tablinks");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }

        // Show the current tab, and add an "active" class to the button that opened the tab
        document.getElementById(settingsName).style.display = "block";
        evt.currentTarget.className += " active";
        if(settingsName == 'account-verification') {
            document.getElementById("triplea-final-step").style.display = 'none';
            document.getElementById("triplea-verify-acnt").style.display = 'block';
        } else {
            document.getElementById("triplea-final-step").style.display = 'block';
            document.getElementById("triplea-verify-acnt").style.display = 'none';
        }
    }

    jQuery(document).ready(function($){

        $('.button-primary.woocommerce-save-button').hide();
        $('.triplea-btn.verify-acnt-btn').hide();

        $('#triplea-final-step').on('click', function(){
            //First Tab Codes
            let merchantKey  = $('#merchantKey').val();
            let clientID     = $('#clientID').val();
            let clientSecret = $('#clientSecret').val();
            let oauthToken, oauthTokenExpiry;

            $('#woocommerce_triplea_payment_gateway_merchant_key').val(merchantKey);
            $('#woocommerce_triplea_payment_gateway_client_id').val(clientID);
            $('#woocommerce_triplea_payment_gateway_client_secret').val(clientSecret);

            //Second Tab options save
            let enablePayment, enableTestMode, enableDebug;

            if ( $('#1').is(":checked") )
                enablePayment = 'yes';
            else
                enablePayment = 'no';

            if ( $('#2').is(":checked") )
                enableTestMode = 'yes';
            else
                enableTestMode = 'no';

            if ( $('#3').is(":checked") )
                enableDebug = 'yes';
            else
                enableDebug = 'no';

            $('#woocommerce_triplea_payment_gateway_enabled').val(enablePayment);
            $('#woocommerce_triplea_payment_gateway_test_mode').val(enableTestMode);
            $('#woocommerce_triplea_payment_gateway_debug_log').val(enableDebug);

            //Third Tab options save
            let cryptoLogo  = $('input[name="cryptoLogo"]:checked').val();;
            let cryptoTitle = $('#formDescription').val();
            $('#woocommerce_triplea_payment_gateway_crypto_logo').val(cryptoLogo);
            $('#woocommerce_triplea_payment_gateway_crypto_text').val(cryptoTitle);

            $('.woocommerce-save-button').click();
        });
    });
</script>