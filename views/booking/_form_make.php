<?php
//кажеться это файл больше не нужен-  вся работа пойдет через _форм.пхп 

use app\components\widget\box\Box;
use app\controllers\Paypal;
use app\models\booking\Booking;
use app\models\booking\BookingTypeOfPaymentEnum;
use app\models\booking\enums\BookingStatusEnum;
use app\models\booking\ExtraPrice;
use app\models\guest\Guest;
use app\models\room\ExtraRoomsRecord;
use app\models\room\Room;
use app\models\season\Tax;
use app\models\user\UserFromProperty;
use app\widgets\MultiplyInput;
use kartik\form\ActiveForm;
use PayPal\Auth\OAuthTokenCredential;
use Stripe\Stripe;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\bootstrap\Modal;
use kartik\select2\Select2;
use yii\web\JsExpression;

$this->registerJsFile('web/js/booking-main-js.js', ['position' => \yii\web\View::POS_END, 'depends' => [
    'app\assets\AppAsset',
],]);

$updateBooking = 0;
if($_GET['id']){
    $updateBooking = $_GET['id'];
}

//use AuthorizeNetAIM;

/*define("AUTHORIZENET_API_LOGIN_ID", "7u5Z4jRW");
define("AUTHORIZENET_TRANSACTION_KEY", "2GR8M33rZ2um6JxE");
define("AUTHORIZENET_SANDBOX", true);
$sale           = new AuthorizeNetAIM;
$sale->amount   = "555.55";
$sale->card_num = '370000000000002';
$sale->exp_date = '11/16';
$sale->ship_to_first_name = 'First name Yii2';
$sale->ship_to_last_name = 'Last name Yii2';
$sale->phone = '555-55-555';
$response = $sale->authorizeAndCapture();
if ($response->approved) {
    var_dump($response);
    $transaction_id = $response->transaction_id;
}*/
?>
    <div class="row" xmlns="http://www.w3.org/1999/html">
        <div class="text-right booking-status col-md-12">Status: <?= $booking->statusTitle ?></div>
    </div>
    <span id="updateBookingId" style="display: none"><?= $updateBooking; ?></span>
<?php
/** @var Booking $booking */
$periodsErrors = $booking->errors['periods'];
// For Search Tax
$groupSeasonId = $booking->rooms[0]->room->group->id;

$roomId = $booking->rooms[0]->room->id;
if(!$roomId){
    if(isset($_POST['RoomId'])){
        $roomId = $_POST['RoomId'];
    }
}
if($_GET['roomId']){
    $roomId = $_GET['roomId'];
}
if(!$booking->tax){

    $from = $_GET['startIndex'];
    $to = $_GET['endIndex'];

    $tax = 0;
    $taxModelForCalendar = (new Query())->select(['*'])->from('vr_tax')->where(['<=', 'from', $from])->andWhere(['>=', 'to', $to])->andWhere(['season_group_id' => $groupSeasonId])->one();
    /*$taxModelForCalendar = Tax::find()
        ->where(['room_id' => $roomId, 'season_group_id' => $groupSeasonId])
        ->andWhere(['<=' ,'from', $from])
        ->andWhere(['>=' ,'to', $to])
        ->asArray()
        ->one();
    if($taxModelForCalendar){
        $booking->tax = $taxModelForCalendar['tax'];
    }*/
    if($taxModelForCalendar){
        $booking->tax = $taxModelForCalendar['tax'];
    }
}

?>
<? $form = ActiveForm::begin([]); ?>
    <div class="row" id="ajax-form-booking">
        <div class="col-md-7">

            <div class="js-guests-container">


                <? Box::begin([
                    'header'       => 'Guests',
                    /*'otherContent' => Html::a('+ Add Guest', '#', [
                        'class' => 'btn btn-default pull-right js-multiply-add',
                    ]),*/
                ]) ?>
                <?
                $guests = Guest::find()->all();
                $guestsMap = ArrayHelper::map($guests, 'id', 'full_name');
                ?>
                <?/*= $form->field($booking, 'guests')->widget(MultiplyInput::className(), [
                'addButtonText'       => '+ Add Guest',
                'itemAttributeNames'  => [
                    'guest_id',
                ],
                'itemAttributeValues' => [
                    'guest_id',
                ],
                'itemContent'         => '
                        <div class="row">
                                <div class="col-md-4">' .
                    Html::dropDownList('rm', NULL, $guestsMap, [
                        'name-template' => '{name[guest_id]}',
                        'class'         => 'form-control',
                        'value'         => '{value[guest_id]}',
                        'prompt'        => 'Select guest...',
                    ]) . '</div>
                                <div class="col-md-4">
                                    <div class="js-guest-info js-multiply-clear-html">

                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <a href="#" class="pull-right delete-input-item js-multiply-remove"><i
                                            class="fa fa-times"></i>Delete Guest</a>
                                </div>
                            </div>
                           ',
            ]) */?>

                <?php
                // The controller action that will render the list
                $url = \yii\helpers\Url::to(['guest']);

                if($_GET['userId']){
                    $userId = (int)($_GET['userId']);
                    $userModel = Guest::find()->where(['id' => $userId])->asArray()->one();
                    $name = "";
                    if(!empty($userModel['name_prefix'])){
                        $name .= $userModel['name_prefix'] . " ";
                    }
                    if(!empty($userModel['full_name'])){
                        $name .= $userModel['full_name'] . " ";
                    }
                    if(!empty($userModel['last_name'])){
                        $name .= $userModel['last_name'] . " ";
                    }
                    if(!empty($userModel['name_suffix'])){
                        $name .= $userModel['name_suffix'] . " ";
                    }

                    $user = [$userModel['id'] => $name];
                } else {
                    if($booking->guestsId && $booking->guests){
                        //$guestModel = Guest::find()->where([])->asArray()->one();
                        $user = [$booking->guestsId => $booking->guests];
                    } else {
                        $user = [0 => ''];
                    }
                }

                if(Yii::$app->user->identity->role != 28 && Yii::$app->user->identity->role != 27 && Yii::$app->user->identity->role != 25){
                    $none_but = [
                        'noResults' => new JsExpression('function () { return "<button type=\"button\" class=\"btn btn-info btn-xs\" id=\"button-ajax-add\">Add new guest</button>"; }'),
                        'errorLoading' => new JsExpression('function () { return "<button type=\"button\" class=\"btn btn-info btn-xs\" id=\"button-ajax-add\">Add new guest</button>"; }'),
                    ];
                } else {
                    $none_but = '';
                }

                echo $form->field($booking, 'guests')->widget(Select2::classname(), [
                    'options' => [
                        //'placeholder' => 'Search Guest ...',
                    ],
                    'id' => 'Guest-select',
                    'data' => $user,
                    'pluginEvents' => [
                        "select2:opening" => "function() { console.log('select2:opening'); }",
                    ],
                    'pluginOptions' => [
                        'allowClear' => true,
                        //'minimumInputLength' => 1,
                        'language' => $none_but,
                        'ajax' => [
                            'url' => $url,
                            'dataType' => 'json',
                            'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                            'results' => new JsExpression('function(data,page) { return {results:data.results};}'),
                        ],
                        'escapeMarkup' => new JsExpression('function (markup) { return markup; }'),
                        'templateResult' => new JsExpression('function(city) { return city.text; }'),
                        'templateSelection' => new JsExpression('function (city) { return city.text; }'),
                    ],
                ]);
                ?>

                <div class="hidden js-guests-infos">
                    <? foreach ($guests as $guest): ?>
                        <div data-guest-id="<?= $guest->id ?>"><?= $guest->getShortInfo() ?></div>
                    <? endforeach; ?>
                </div>
                <div class="add-ajax-guest"></div>
                <? Box::end() ?>

            </div>
            <div class="js-rooms-container">

                <? Box::begin([
                    'header' => 'Booking detail',
                    // tomake Реализовать кнопку согласно поведению
//                    'otherContent' => Html::a('+ Add Room', '#', [
//                        'class' => 'btn btn-default pull-right js-multiply-add',
//                    ]),
                ]) ?>

                <? if ($periodsErrors): ?>
                    <? foreach ($periodsErrors as $error): ?>
                        <div class="alert alert-danger">
                            <?= $error ?>
                        </div>
                    <? endforeach; ?>
                <? endif; ?>
                <?
                $guestsCountDropDown = [];
                for ($i = 1; $i <= 15; $i++) {
                    $guestsCountDropDown[$i] = $i . ' ' . ($i == 1 ? 'guest' : 'guests');
                }

                /*$table = 'vr_booking_rooms';
                $guest_count =  \Yii::$app->db->createCommand("SELECT * FROM $table WHERE booking_id = :id")
                    ->bindValue(':id', $booking->id)
                    ->execute();
                var_dump($guest_count);*/

                // Search guest count
                $guest_count = (new \yii\db\Query())
                    ->select(['guests_count'])
                    ->from('vr_booking_rooms')
                    ->where(['booking_id' => $booking->id])
                    ->one();
                $availableRoomsMap = ArrayHelper::map(Room::find()->andWhere(['active_room' => 1])->all(), 'id', 'title');
                $availableRoomsMapArray = [];
                foreach($availableRoomsMap as $key => $val){
                    $availableRoomsMapArray[$key] = mb_strimwidth($val, 0, 40, "...");
                }
                // START For Update Price ROLE Users
                if(Yii::$app->user->identity->role != 28 && Yii::$app->user->identity->role != 27 && Yii::$app->user->identity->role != 25){
                    $check_in_from = 'check-in-from';
                    $check_out_to = 'check-out-to';
                    $select_room_id = 'select-room-id';
                    $select_guest_count = 'select-guest-count';
                } else {
                    $check_in_from = 'none1';
                    $check_out_to = 'none2';
                    $select_room_id = 'none3';
                    $select_guest_count = 'none4';
                }
                // END For Update Price ROLE Users
                ?>
                <?= $form->field($booking, 'rooms')->widget(MultiplyInput::className(), [
                    'addButtonText'       => '+ Add Room',
                    'itemAttributeNames'  => [
                        'from',
                        'to',
                        'room_id',
                        'guests_count',
                    ],
                    'itemAttributeValues' => [
                        'fromDate',
                        'toDate',
                        'room_id',
                        'guests_count',
                    ],
                    'itemContent'         => '
 <div class="text-right"><a href="#" class=" delete-input-item js-multiply-remove"><i class="fa fa-times"></i>Delete Room</a></div>
                                <div class="row">
                                  
                                    <div class="col-md-6">
                                        <div class="">
                                            <label class="control-label">Check
                                                in:</label>
                                            <input name-template="{name[from]}" type="text"
                                                   value="{value[fromDate]}"
                                                   class="form-control fm-date" id="'.$check_in_from.'">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="">
                                            <label class="control-label"
                                            >Out:</label>
                                            <input name-template="{name[to]}" type="text"
                                                   value="{value[toDate]}"
                                                   class="form-control fm-date" id="'.$check_out_to.'">
                                        </div>
                                    </div>
                                 
                                 
                                </div>
                    
                                <div>Room:</div>
                                <div class="row">
                                    <div class="col-md-6">' .
                        Html::dropDownList('rm', NULL, $availableRoomsMapArray, [
                            'name-template' => '{name[room_id]}',
                            'class'         => 'form-control',
                            'value'         => '{value[room_id]}',
                            'prompt'        => 'Select room...',
                            'id'            => $select_room_id,
                            'options'       =>  [$roomId => ['Selected'=>true]],
                        ]) . '
                                    </div>
                                    <div class="col-md-6">' .
                        Html::dropDownList('gc', NULL, $guestsCountDropDown, [
                            'name-template' => '{name[guests_count]}',
                            'class'         => 'form-control',
                            'value'         => '{value[guests_count]}',
                            'prompt'        => 'Select count of guests...',
                            'id'            => $select_guest_count,
                            'options'       =>  [$guest_count['guests_count'] => ['Selected'=>true]],
                        ]) . '
                                    </div>
                                </div>
                 
                ',
                ]) ?>
                <? Box::end() ?>

            </div>

            <? Box::begin([
                'header' => 'Notes',
            ]) ?>
            <?php if($booking->isNewRecord): ?>
                <?= $form->field($booking, 'notes')->textarea([
                    'class' => 'ff-line-text-input',
                    'rows'  => 4,
                ]); ?>
            <?php else: ?>
                <div id="notesIframeAjax" style="background: #F0F0F0; height: 100px; overflow-y: scroll; overflow-x: hidden">
                    <?php if(count($notesAll) > 0): ?>
                        <?php
                        $hr = 1;
                        $notesCount = count($notesAll);
                        ?>
                        <?php foreach($notesAll as $notes): ?>
                            <?php
                            /*$weekInNow = date("l",time());
                            $dayWeek = date("l",$notes['time']);*/
                            $weekInNow = date("d-m-Y",time());
                            $dayWeek = date("d-m-Y",$notes['time']);
                            if($weekInNow == $dayWeek){
                                $dayWeek = "Today";
                            } else {
                                $dayWeek = date('m-d-Y', $notes['time']);
                            }
                            $avatar = UserFromProperty::find()->where(['id' => $notes['user_id']])->asArray()->one();
                            if($avatar['avatar'] == '' || $avatar['avatar'] == NULL){
                                $avatar = 'default_logo.png';
                                $all_avatar = $avatar;
                            } else {
                                $all_avatar = $notes['user_id'] . "/" . $avatar['avatar'];
                            }
                            $allAboutUser = $BookingModel = (new Query())->select(['username', 'second_name'])->from('vr_user')->where(['id' => $notes['user_id']])->one();
                            $nameComment = '';
                            if($allAboutUser){
                                $nameComment = $allAboutUser['username'] . ' ' . $allAboutUser['second_name'];
                            }
                            ?>
                            <div class="row">
                                <div class="col-md-1"><img src="/web/user-avatar/<?= $all_avatar; ?>" alt="" style="width: 45px; border-radius: 100px"></div>
                                <div class="col-md-8"><b><?= $nameComment; ?></b></div>
                                <div class="col-md-3"><?= date("g:i A",$notes['time']) . " " . $dayWeek; ?></div>
                            </div>
                            <div class="row">
                                <div class="col-md-1"></div>
                                <div class="col-md-11"><?= $notes['text']; ?></div>
                            </div>
                            <br>
                            <?php if($hr == $notesCount): ?>

                            <?php else: ?>
                                <hr style="border-bottom: 1px dotted #dbd6d6">
                                <br>
                            <?php endif; ?>

                            <?php $hr++; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <br>
                <div class="form-inline">
                    <input type="text" class="form-control mb-2 mr-sm-2 mb-sm-0" id="sendNotesInput" value="" placeholder="Press Enter to post comments" style="width: 89%">
                    <input type="hidden" name="userId" id="sendNotesUserId" value="<?= Yii::$app->user->getId(); ?>">
                    <input type="hidden" name="bookingId" id="sendBookingId" value="<?= $booking->id ?>">
                    <button type="button" class="btn btn-primary pull-right" id="sendNotesBooking">Post</button>
                </div>
            <?php endif; ?>
            <? Box::end() ?>
        </div>
        <?php if(Yii::$app->user->identity->role != 28 && Yii::$app->user->identity->role != 27 && Yii::$app->user->identity->role != 25): ?>
        <div class="col-md-5 price-total-container js-price-total-container">
            <? Box::begin([
                'header' => 'Money',
                'otherContent' => Html::submitButton('Аdd a tax adjustment', [
                    'class' => 'btn btn-primary pull-right btn-xs',
                    'id' => 'addTaxAdjustment',
//                'class' => 'btn btn-primary pull-right js-recalculate',
                ]),
            ]) ?>
            <div class="row">
                <div class="col-md-9">Rooms</div>
                <div class="col-md-3 text-right" id="price-text-right">$<?= $booking->room_price ? $booking->room_price : 0 ?></div>
            </div>
            <div class="row">
                <div class="col-md-9">Extras</div>

                <div class="col-md-3 text-right" id="extras-text-right">$<?= $booking->room_extras ? $booking->room_extras : 0 ?></div>
            </div>
            <div class="row">
                <div class="col-md-9">Adjustment</div>

                <div class="col-md-3 text-right"><?= $form->field($booking, 'adjustment')->textInput([
                        'class' => 'ff-line-text-input fm-price-negative text-right ',
                    ])->label(FALSE) ?></div>
            </div>
            <div class="row" id="adjustmentTax" style="display: none">
                <div class="col-md-9">Tax adjustment(%)</div>

                <div class="col-md-3 text-right"><?= $form->field($booking, 'adjustment_tax')->textInput([
                        'class' => 'ff-line-text-input TaxInput form-control',
                    ])->label(FALSE) ?></div>
            </div>

            <?= $form->field($booking, 'adjustment_description')->textInput([
                'class'       => 'ff-line-text-input',
                'placeholder' => 'Description',
            ])->label(FALSE) ?>
            <div class="row">
                <div class="col-md-3">
                    <?= $form->field($booking, 'tax')->textInput([
                        //'class' => 'ff-line-text-input fm-number-my',
                        'class' => 'ff-line-text-input TaxInput',
                    ]) ?>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <div class="col-md-8 text-right">Subtotal:</div>
                        <? /** @var integer $subtotalPrice */ ?>
                        <div class="col-md-4 text-right" id="subtotal-text-right">$<?= $booking->subtotalPrice ? $booking->subtotalPrice : 0 ?></div>
                    </div>

                    <div class="row">
                        <div class="col-md-8 text-right">TOTAL:</div>
                        <? /** @var integer $totalPrice */ ?>
                        <?php $total_price = $booking->totalPrice; ?>
                        <div class="col-md-4 text-right" id="total-text-right">$<?= $total_price ? $total_price : 0 ?></div>
                    </div>
                </div>
            </div>
            <? Box::end() ?>

            <? Box::begin([
                'header' => 'Status',
            ]) ?>

            <?= $form->field($booking, 'status')->dropDownList(BookingStatusEnum::getArrValues())->label(FALSE) ?>
            <?php //var_dump(BookingStatusEnum::getArrValues()); ?>

            <? Box::end() ?>

            <? Box::begin([
                'header' => 'Payments'
            ]) ?>

            <div class="row text-center">
                <button type="button" class="btn btn-secondary btn-sm" id="button-booking-add">Add</button>
                <button type="button" class="btn btn-secondary btn-sm" id="button-booking-add-credit">Add credit</button>
                <button type="button" class="btn btn-secondary btn-sm" id="button-booking-resend-invoice" data-bookingid="<?= $booking->id; ?>">Resend Invoice</button>
            </div>

            <table class="table table-hover" id="BookingTableUpdate" style="border-collapse: separate;">
                <thead>
                <tr>
                    <th>Amount</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Notes</th>
                </tr>
                </thead>
                <tbody>
                <?php if($booking->bookingPayments): ?>
                    <?php foreach($booking->bookingPayments as $key => $payment): ?>
                        <tr>
                            <td style="<?= $payment->credit ? 'background: #00ff4e; color: black' : '' ?>"><?= $payment->amount ?></td>
                            <td style="<?= $payment->credit ? 'background: #00ff4e; color: black' : '' ?>"><?= BookingTypeOfPaymentEnum::getLabelName($payment->type_of_payment); ?></td>
                            <td style="<?= $payment->credit ? 'background: #00ff4e; color: black' : '' ?>"><?= date('m/d/Y H:i:s', $payment->time_of_payment); ?></td>
                            <td style="<?= $payment->credit ? 'background: #00ff4e; color: black' : '' ?>"><?= $payment->who_entered ?></td>
                            <td style="<?= $payment->credit ? 'background: #00ff4e; color: black' : '' ?>"><?= $payment->notes_entered ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <!--<div class="row">
            <div class="col-md-12">
                <?/*= $form->field($booking, 'full_amount_payment')->textInput([
                    'class' => 'ff-line-text-input',
                ]) */?>
            </div>
        </div>
        <div class="row" id="id-type-of-payment" style="display: none;">
            <div class="col-md-6">
                <?/*= $form->field($booking, 'type_of_payment')->dropdownList(BookingTypeOfPaymentEnum::getArrValues()) */?>
            </div>
        </div>-->


            <!--NOT REALISED YET-->

            <!--<div id="paypal-button"></div>

            <script src="https://www.paypalobjects.com/api/checkout.js" data-version-4></script>

            <script>
                paypal.Button.render({

                    env: 'sandbox', // Optional: specify 'sandbox' environment

                    client: {
                        sandbox: 'ARBk9_tVBpJCWYxo8GHSu7o2YoQOw6c1bR_C71TOY51MwxNGT-WJs9xmnmHY_s1H2rcQb1zZsUKHwex9'
                    },

                    payment: function() {

                        var env    = this.props.env;
                        var client = this.props.client;

                        return paypal.rest.payment.create(env, client, {
                            transactions: [
                                {
                                    amount: { total: 13.00, currency: 'USD' },
                                    description : "This is the payment transaction description."
                                }
                            ]
                        });
                    },

                    commit: true, // Optional: show a 'Pay Now' button in the checkout flow

                    onAuthorize: function(data, actions) {

                        //alert("very Good");
                        // Optional: display a confirmation page here

                        return actions.payment.execute().then(function() {
                            // Show a success page to the buyer
                        });
                    }

                }, '#paypal-button');
            </script>-->



            <? Box::end() ?>
            <?php endif; ?>
        </div>
    </div>
<?php if(Yii::$app->user->identity->role != 28 && Yii::$app->user->identity->role != 27 && Yii::$app->user->identity->role != 25): ?>
    <input type="hidden" id="daySeasonId" value="<?= $_SESSION['daySeasonId']; ?>">
    <? Box::begin([
        'header' => 'Extras',
    ]) ?>
    <div class="renderAjaxExtraForRoom">
        <label class="control-label">Extras for Room</label><br>

        <?php
        if($_SESSION['daySeasonId']){
            $daySeasonId = $_SESSION['daySeasonId'];
            unset($_SESSION['daySeasonId']);
        }
        $data = ExtraRoomsRecord::find()
            ->with('extra')
            ->where(['room_id' => $_GET['roomId']])
            ->all();
        foreach($data as $key => $value){
            // Search not season from extra
            $viewExtra = ExtraPrice::find()->where(['season_id' => $daySeasonId, 'extra_id' => $value->extra_id])->one();
            if($viewExtra == null){
                continue;
            }
            ?>
            <div class="checkbox <?= $value->extra->is_compulsory ? 'disabled' : ""; ?>">
                <label>
                    <input type="checkbox" checked <?= $value->extra->is_compulsory ? 'disabled' : ""; ?> class="roomCheckedForBooking" data-roomCheked="<?= $value->id ?>" data-roomId="<?= $_GET['roomId'] ?>" data-extraId="<?= $value->extra_id ?>"/> <?= $value->extra->name ?>
                </label>
            </div>

            <?php
        }
        ?>
    </div>
    <? Box::end() ?>

    <? Box::begin([
        'header' => 'Conversations',
    ]) ?>
    NOT REALISED YET
    <? Box::end() ?>
    <div class="form-group">
        <?= Html::submitButton($booking->isNewRecord ? 'Create' : 'Update', ['class' => $booking->isNewRecord ? 'btn btn-success' : 'btn btn-primary', 'id' => 'ajaxBookingCreateFromMinStay']) ?>
    </div>
<?php endif; ?>
<? ActiveForm::end() ?>


<?php Modal::begin([
    'id' => 'email-send-grid-modal',
    'header' => '<h4 class="modal-title">Email</h4>',
    'footer' => '<a href="#" class="btn btn-primary" data-dismiss="modal">Close</a>',

]); ?>

    <div class="row">
        <div class="col-lg-12"><h3 style="text-align: center">Email sent</h3></div>
    </div>


<?php Modal::end(); ?>