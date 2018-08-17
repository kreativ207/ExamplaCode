<form class="form" action="paypal_ec_mark.php" method="POST">
    <div class="row-fluid">
        <div class="span6 inner-span">
            <p class="lead">Shipping Address</p>
            <table>
                <input type="hidden" name="L_PAYMENTREQUEST_0_AMT" value="<?php echo $_POST["PAYMENTREQUEST_0_AMT"]; ?>">
                <tr><td width="30%">First Name</td><td><input type="text" name="L_PAYMENTREQUEST_FIRSTNAME" value="Alegra"></input></td></tr>
                <tr><td>Last Name:</td><td><input type="text" name="L_PAYMENTREQUEST_LASTNAME" value="Valava"></input></td></tr>
                <tr><td>Address</td><td><input type="text" name="PAYMENTREQUEST_0_SHIPTOSTREET" value="55 East 52nd Street"></input></td></tr>
                <tr><td>Address 1</td><td><input type="text" name="PAYMENTREQUEST_0_SHIPTOSTREET2" value="21st Floor"></input></td></tr>
                <tr><td>City:</td><td><input type="text" name="PAYMENTREQUEST_0_SHIPTOCITY" value="New York" ></input></td></tr>
                <tr><td>State:</td><td><input type="text" name="PAYMENTREQUEST_0_SHIPTOSTATE" value="NY" ></input></td></tr>
                <tr><td>Postal Code:</td><td><input type="text" name="PAYMENTREQUEST_0_SHIPTOZIP" value="10022" ></input></td></tr>
                
                <tr><td>Telephone:</td><td><input type="text" name="PAYMENTREQUEST_0_SHIPTOPHONENUM" value="" maxlength="12"></input></td></tr>

                <tr><td colspan="2"><p class="lead">Shipping Detail:</p></td></tr>
                <tr><td>Shipping Type: </td><td><select name="shipping_method" id="shipping_method" style="width: 250px;" class="required-entry">
                            <optgroup label="United Parcel Service" style="font-style:normal;">
                                <option value="2.00">
                                    Worldwide Expedited - $2.00</option>
                                <option value="3.00">
                                    Worldwide Express Saver - $3.00</option>
                            </optgroup>
                            <optgroup label="Flat Rate" style="font-style:normal;">
                                <option value="0.00" selected>
                                    Fixed - $0.00</option>
                            </optgroup>
                        </select><br>
                    </td></tr>
                <tr><td colspan="2"><p class="lead">Payment Methods:</p></td></tr>
                <tr><td colspan="2">
                        <input id="paypal_payment_option" value="paypal_express" type="radio" name="paymentMethod" title="PayPal Express Checkout" class="radio" checked>
                        <img src="https://fpdbs.paypal.com/dynamicimageweb?cmd=_dynamic-image&amp;buttontype=ecmark&amp;locale=en_US" alt="Acceptance Mark" class="v-middle">&nbsp;
                        <a href="https://www.paypal.com/us/cgi-bin/webscr?cmd=xpt/Marketing/popup/OLCWhatIsPayPal-outside" onclick="javascript:window.open('https://www.paypal.com/us/cgi-bin/webscr?cmd=xpt/Marketing/popup/OLCWhatIsPayPal-outside','olcwhatispaypal','toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, ,left=0, top=0, width=400, height=350'); return false;">What is PayPal?</a>
                    </td></tr>
                <tr><td colspan="2"><input id="p_method_paypal_express" value="credit_card" type="radio" name="paymentMethod" title="PayPal Express Checkout" class="radio" disabled>&nbsp;Credit Card</td></tr>
                <tr><td>&nbsp;</td></tr>

            </table>
            <input type="submit" id="placeOrderBtn" class="btn btn-primary btn-large" name="PlaceOrder" value="Place Order" />
        </div>
    </div>
</form>
</div>
<div class="span3">
</div>
<script src="//www.paypalobjects.com/api/checkout.js" async></script>
<script type="text/javascript">
    window.paypalCheckoutReady = function () {
        paypal.checkout.setup('ARBk9_tVBpJCWYxo8GHSu7o2YoQOw6c1bR_C71TOY51MwxNGT-WJs9xmnmHY_s1H2rcQb1zZsUKHwex9', {
            button: 'placeOrderBtn',
            environment: 'sandbox',
            condition: function () {
                return document.getElementById('paypal_payment_option').checked === true;
            }
        });
    };
</script>