<div class="wrap">
    <?php if ($successMessage): ?>
        <div class="updated"><p><?php echo $successMessage; ?></p></div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="error"><p><?php echo $errorMessage; ?></p></div>
    <?php endif; ?>

    <div class="welcome-panel">
        <h3>Getting Started</h3>
        <p>Create a Square account if you don't have one</p>

        <p>You need a Square account to register an application with Square. If you don't have an account, go to <a target="_blank" href="https://squareup.com/signup">https://squareup.com/signup</a> to create one.
            Register your application with Square
        </p>

        <p>
            Then go to <a  target="_blank" href="https://connect.squareup.com/apps">https://connect.squareup.com/apps</a> and sign in to your Square account. Then <b>click New Application</b> and enter a name for your application and Create App.

            The application dashboard displays your new app's credentials. One of these credentials is the personal access token. This token gives your application full access to your own Square account. 
        </p>
        
        
        <form method="post">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th><strong>Square Access Token</strong></th>
                        <td>
                            <input class="regular-text" value="<?php echo (isset($_POST['woo_square_access_token'])) ? sanitize_text_field($_POST['woo_square_access_token']) : get_option('woo_square_access_token'); ?>" name="woo_square_access_token" type="text"/>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" value="Authorize" class="button button-primary">
            </p>
        </form>
    </div>

    <?php if (get_option('woo_square_access_token')): ?>

        <div class="welcome-panel">
            <h3>Woo Square Settings</h3>
            <?php if ( $currencyMismatchFlag ){ ?>
                <br/>
                <div id="woo_square_error" class="error" style="background: #ddd;">
                    <p style="color: red;font-weight: bold;">The currency code of your Square application [ <?php echo $squareCurrencyCode ?> ] does not match WooCommerce [ <?php echo $wooCurrencyCode ?> ]</p>
                </div>
            <?php }?>
            <form method="post" <?php if ($currencyMismatchFlag): ?> style="opacity:0.5;pointer-events:none;" <?php endif; ?> >
                <input type="hidden" value="1" name="woo_square_settings" />
                <table class="form-table">
                    <tbody>
                        <?php if (get_option('woo_square_location_id') != '' && get_option('woo_square_location_id') != 'me' ): ?>
                            <tr>
                                <th scope="row"><label>Select Location</label></th>
                                <td>
                                        <select name="woo_square_location_id">
                                            <?php foreach (get_option('woo_square_locations') as $key => $location): ?>
                                                    <option <?php if (get_option('woo_square_location_id') == $key): ?>selected=""<?php endif; ?> value="<?php echo $key; ?>"> <?php echo $location; ?> </option>
                                            <?php endforeach; ?>
                                        </select>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <th scope="row"><label>Auto Synchronize</label></th>
                            <td>
                                <label><input type="radio" <?php echo (get_option('woo_square_auto_sync'))?'checked':''; ?> value="1" name="woo_square_auto_sync"> On </label>
                                <label><input type="radio" <?php echo (get_option('woo_square_auto_sync'))?'':'checked'; ?> value="0" name="woo_square_auto_sync"> Off </label>
                            </td>
                        </tr>
                        <tr id="auto_sync_duration_div" style="<?php echo (get_option('woo_square_auto_sync'))?'':'display: none;'?>">
                            <th scope="row">Auto Sync each</th>
                            <td>
                                <select name="woo_square_auto_sync_duration">
                                    <option <?php if (get_option('woo_square_auto_sync_duration') == '60'): ?>selected=""<?php endif; ?> value="60"> 1 hour </option>
                                    <option <?php if (get_option('woo_square_auto_sync_duration') == '720'): ?>selected=""<?php endif; ?> value="720"> 12 hours </option>
                                    <option <?php if (get_option('woo_square_auto_sync_duration') == '1440'): ?>selected=""<?php endif; ?> value="1440"> 24 hours </option>
                                </select>
                                <div class="ws-pro-now"><span>This Feature is available in PRO Version</span><a href="https://goo.gl/LEJeQG">GET WOO SQUARE PRO</a></div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label>Merging Option</label></th>
                            <td>
                                <label><input type="radio" <?php echo (get_option('woo_square_merging_option') == "1")?'checked':''; ?> value="1" name="woo_square_merging_option"> Woo commerce product Override square product </label><br><br>
                                <label><input type="radio" <?php echo (get_option('woo_square_merging_option') == "2")?'checked':''; ?> value="2" name="woo_square_merging_option"> Square product Override Woo commerce product </label><br><br>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label>Would you like to synchronize your product on every product edit or update ?</label></th>
                            <td>
                                <label><input type="radio" <?php echo (get_option('sync_on_add_edit') == "1")?'checked':''; ?> value="1" name="sync_on_add_edit"> Yes </label><br><br>
                                <label><input type="radio" <?php echo (get_option('sync_on_add_edit') == "2")?'checked':''; ?> value="2" name="sync_on_add_edit"> No </label><br><br>
                            </td>
                        </tr>
                        


                    </tbody>
                </table>
                <p class="submit">
                    <input type="submit" value="Save Changes" class="button button-primary">
                </p>
            </form>

        </div>

        <div class="welcome-panel" <?php if ($currencyMismatchFlag): ?> style="opacity:0.5;pointer-events:none;" <?php endif; ?>>
                <a class="button button-primary button-hero load-customize hide-if-no-customize" href="javascript:void(0)" id="manual_sync_wootosqu_btn" > Synchronize Woo To Square </a>
                <a class="button button-primary button-hero load-customize hide-if-no-customize" href="javascript:void(0)" id="manual_sync_squtowoo_btn" > Synchronize Square To Woo </a>
            <br><br>
        </div>

    </div>



    <div class="cd-popup" role="alert">
        <div class="cd-popup-container">
            <div id="sync-loader">
                <img width=50%; height=50% src="<?php echo plugins_url( '_inc/images/ring.gif', dirname(__FILE__) );?>" alt="loading"  >
            </div>
            <div id="sync-error"></div>
            <div id="sync-content"  style="display:none;">
                <div id="sync-content-woo">
                </div>
                <div id="sync-content-square">
                </div>
            </div>
            <ul class="cd-buttons start">
                <li><button id="start-process"  href="#">Start Synchronization</button></li>
                <li><button class="cancel-process" href="#0">Cancel</button></li>
            </ul>
            <ul class="cd-buttons end">
                <li><button id="sync-processing" href="#0">Close</button></li>
            </ul>
            <a href="#0" class="cd-popup-close img-replace"></a>   
        </div> <!-- cd-popup-container -->
    </div> <!-- cd-popup -->
  

<?php endif; ?>