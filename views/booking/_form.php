<?php
use app\components\widget\box\Box;
use app\controllers\BookingController;
use app\controllers\Paypal;
use app\models\booking\Booking;
use app\models\booking\BookingTypeOfPaymentEnum;
use app\models\booking\enums\BookingStatusEnum;
use app\models\booking\ExtraPrice;
use app\models\guest\Guest;
use app\models\room\ExtraRoomsRecord;
use app\models\room\PetsEnum;
use app\models\room\Room;
use app\models\season\Tax;
use app\models\user\UserFromProperty;
use app\widgets\MultiplyInput;
use kartik\checkbox\CheckboxX;
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
use yii\helpers\VarDumper;


// в этом файле добавил чтобы не отображалась кнопка + Add Room
$this->registerJsFile('web/js/booking-main-js.js', ['position' => \yii\web\View::POS_END, 'depends' => [
    'app\assets\AppAsset',
],]);
$this->registerJs('updateRoomCalendarPriceAndExtra()');

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

if($_GET['roomId']){
    $roomId = $_GET['roomId'];
}


if(!$booking->tax){

    if($_GET['startIndex'] && $_GET['endIndex']){
        $from = $_GET['startIndex'];
        $to = $_GET['endIndex'];
    } elseif($_SESSION['startIndex'] && $_SESSION['endIndex']){
        $from = $_SESSION['startIndex'];
        $to = $_SESSION['endIndex'];
    }

    unset($_SESSION['startIndex']);
    unset($_SESSION['endIndex']);

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
    if(isset($_SESSION['taxable_for_room']) && $_SESSION['taxable_for_room'] == 0){
        $booking->tax = 0;
    }
}

?>
<?php $form = ActiveForm::begin([]); ?>
<div class="row" id="ajax-form-booking">
    <div class="col-md-7">

        <div class="js-guests-container">


            <?php Box::begin([
                'header'       => 'Guests',
                /*'otherContent' => Html::a('+ Add Guest', '#', [
                    'class' => 'btn btn-default pull-right js-multiply-add',
                ]),*/
            ]) ?>
            <?php
            $guests = Guest::find()->all();
            $guestsMap = ArrayHelper::map($guests, 'id', 'full_name');
            ?>
            <?php /*= $form->field($booking, 'guests')->widget(MultiplyInput::className(), [
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
            //unset($_SESSION['guestForBooking']);
            if($_GET['userId'] || $_COOKIE['guestForBooking']){
                if($_GET['userId']){
                    $userId = (int)($_GET['userId']);
                } else {
                    $userId = (int)$_COOKIE['guestForBooking'];
                }

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
                    $guestModel = Guest::find()->where(['id' => $booking->guestsId])->asArray()->one();
                    $name = "";
                    if(!empty($guestModel['name_prefix'])){
                        $name .= $guestModel['name_prefix'] . " ";
                    }
                    if(!empty($guestModel['full_name'])){
                        $name .= $guestModel['full_name'] . " ";
                    }
                    if(!empty($guestModel['last_name'])){
                        $name .= $guestModel['last_name'] . " ";
                    }
                    if(!empty($guestModel['name_suffix'])){
                        $name .= $guestModel['name_suffix'] . " ";
                    }
                    $user = [$booking->guestsId => $name];

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
                        "select2:selecting" => "function(evt) {
                            var idGuest = evt.params.args.data.id;
                            var nameGuest = evt.params.args.data.text;
                                            //console.log(  evt.params.args.data  );
                        }",
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

                    <?php Box::begin([
                'header' => 'Send message to guest',
                    ]);

//          echo $form->field($model, 'message')->textarea([
//                        'data-name'=>'Message',
//                        'class'       => 'ff-line-text-input',
//                        'placeholder' => '...',
//                        'cols' => 5,
//                        'rows' => 5
//                    ]);

        echo Html::label('Subject');
        echo Html::input('text' ,'mailsubj','', ['id'=>'booking-mailsubj','class' => 'form-control']);
        echo Html::label('Message');
        echo Html::textarea('mailtext' , '', ['id'=>'booking-mailtext','class' => 'form-control']);
        echo '<p id="booking-mail-message"></p>';
        echo Html::button('Send message',['id'=>'booking-mailsend', 'class' => 'btn btn-primary pull-right']);

        Box::end();
        ?>

        <div class="js-rooms-container">

            <?php Box::begin([
                'header' => 'Booking detail',
                // tomake Реализовать кнопку согласно поведению
//                    'otherContent' => Html::a('+ Add Room', '#', [
//                        'class' => 'btn btn-default pull-right js-multiply-add',
//                    ]),
            ]) ?>

            <?php if ($periodsErrors): ?>
                <?php foreach ($periodsErrors as $error): ?>
                    <div class="alert alert-danger">
                        <?= $error ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php
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
 <div class="text-right"><!--<a href="#" class=" delete-input-item js-multiply-remove"><i class="fa fa-times"></i>Delete Room</a>--></div>
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
                    Html::dropDownList('rm', NULL, $availableRoomsMap, [
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
            <span class="error_conflict_period_booking" style="color: red"></span>
            <? Box::end() ?>
        </div>
        <!-- Pet -->
      
        <div class="renderAjaxExtraForPetFriendly">

            <?php
            $modelRoom = (new Query())->select(['*'])->from('vr_room')->where(['id' => $roomId])->one();
            
            echo '<input type="hidden" id="pet_friendly" value="'.$modelRoom['pet_friendly'].'">';
            
            if($_GET['roomId'] || $_SESSION['getRoomIdFromBooking']):
                if($_GET['roomId']){
                    $room_id_pets = $_GET['roomId'];
                } elseif($_SESSION['getRoomIdFromBooking']){
                    $room_id_pets = $_SESSION['getRoomIdFromBooking'];
                }
                /** @var Room $modelRoom */
                //$modelRoom = (new Query())->select(['*'])->from('vr_room')->where(['id' => (int)$room_id_pets])->one();
              
                //var_dump($modelRoom);
                
                if($modelRoom['pet_max_amount']){
                    $pet_max_amount = $modelRoom['pet_max_amount'];
                } else {
                    $pet_max_amount = 0;
                }
                
                
                if($modelRoom['pet_friendly']):
                //if( isset($modelRoom['pet_friendly'])):
                    ?>
                    <?php Box::begin([
                        'header' => 'Pets',
                    ]) ?>

                    <div class="row">
                        <span class="petMaxAmountHidden" style="display: none"><?= $pet_max_amount; ?></span>
                        <div class="pet-friendly-checkbox">
                            <div class="col-md-5">
                                <!--<div class="checkbox">
                                    <label><input type="checkbox" name="booking-pets-check" id="bookingPetsCheckId">Pet Friendly</label>
                                </div>-->
                                <?php
                                $pets = explode(';',$modelRoom['pet_type']);
                                if(is_array($pets)):
                                    ?>
                                    <?php foreach($pets as $pet): ?>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="checkbox">
                                                <label class="checkBox">
                                                    <input type="checkbox" name="booking-pets-value[<?= $pet; ?>]" class="bookingPetsName checked-control" value="<?= $pet; ?>">
                                                    <span>&nbsp;<?= PetsEnum::getStatusName($pet); ?></span>
                                                </label>
                                            </div>
                                        </div>
                                        <p></p>
                                        <div class="col-md-6">
                                            <select class="selectpicker selectpickerFromBooking bookingPetsCount-<?= $pet; ?>" data-petid="<?= $pet; ?>" disabled name="booking-pets-count[<?= $pet; ?>]">
                                                <?php for($start = 0; $start <= $pet_max_amount; $start++): ?>
                                                    <option><?= $start; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <span class="errorPetsCount" style="color: red"></span>
                    <?php Box::end() ?>
                    <?php endif; ?>
                <?php elseif ($booking->rooms[0]['room_id'] && $modelRoom['pet_friendly']): ?>
                <?php
                Box::begin([
                    'header' => 'Pets',
                ]) ?>
                    <div class="row">
                        <div class="pet-friendly-checkbox">
                            <div class="col-md-5">
                                <?php
                                if($booking->booking_pets_type){
                                    $petsInBooking = explode(';',$booking->booking_pets_type);
                                }
                                $modelRoom = (new Query())->select(['*'])->from('vr_room')->where(['id' => $booking->rooms[0]['room_id']])->one();
                                $pets = explode(';',$modelRoom['pet_type']);
                                if($modelRoom['pet_max_amount']){
                                    $pet_max_amount = $modelRoom['pet_max_amount'];
                                } else {
                                    $pet_max_amount = 0;
                                }
                                $bookingPetsCount = \app\controllers\BookingController::objectToArray(json_decode($booking['booking_pets_count']));
                                ?>
                                <span class="petMaxAmountHidden" style="display: none"><?= $pet_max_amount; ?></span>
                                <!--<div class="checkbox">
                            <label><input type="checkbox" name="booking-pets-check" id="bookingPetsCheckId" <?/*= $booking->booking_pets_type ? 'checked' : '' */?>>Pet Friendly</label>
                        </div>-->
                                <?php
                                if(is_array($pets) && $pets[0] != ''): ?>
                                    <?php if($petsInBooking): ?>
                                        <?php foreach($pets as $pet): ?>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="checkbox">
                                                        <label class="checkBox">
                                                            <input type="checkbox" name="booking-pets-value[<?= $pet; ?>]" class="bookingPetsName checked-control" <?= in_array($pet, $petsInBooking) ? 'checked' : '' ?> value="<?= $pet; ?>">
                                                            <span>&nbsp;<?= PetsEnum::getStatusName($pet); ?></span>
                                                        </label>
                                                    </div>
                                                </div>
                                                <p></p>
                                                <div class="col-md-6">
                                                    <select class="selectpicker selectpickerFromBooking bookingPetsCount-<?= $pet; ?>" data-petid="<?= $pet; ?>" <?php if(!array_key_exists($pet , $bookingPetsCount)){echo 'disabled';}?> name="booking-pets-count[<?= $pet; ?>]">
                                                        <?php for($start = 0; $start <= $pet_max_amount; $start++): ?>
                                                            <option <?php
                                                            if(array_key_exists($pet , $bookingPetsCount) && $bookingPetsCount[$pet] == $start){
                                                                echo 'selected';
                                                            }
                                                            ?>
                                                            >
                                                                <?= $start; ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <?php foreach($pets as $pet): ?>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="checkbox">
                                                        <label class="checkBox">
                                                            <input type="checkbox" name="booking-pets-value[<?= $pet; ?>]" class="bookingPetsName checked-control" value="<?= $pet; ?>">
                                                            <span>&nbsp;<?= PetsEnum::getStatusName($pet); ?></span>
                                                        </label>
                                                    </div>
                                                </div>
                                                <p></p>
                                                <div class="col-md-6">
                                                    <select class="selectpicker selectpickerFromBooking bookingPetsCount-<?= $pet; ?>" data-petid="<?= $pet; ?>" <?php if(!array_key_exists($pet , $bookingPetsCount)){echo 'disabled';}?> name="booking-pets-count[<?= $pet; ?>]">
                                                        <?php for($start = 0; $start <= $pet_max_amount; $start++): ?>
                                                            <option <?php
                                                            if(array_key_exists($pet , $bookingPetsCount) && $bookingPetsCount[$pet] == $start){
                                                                echo 'selected';
                                                            }
                                                            ?>
                                                            >
                                                                <?= $start; ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <span class="errorPetsCount" style="color: red"></span>
                <?php Box::end() ?>
                <?php endif; ?>
        </div>

        <?php Box::begin([
            'header' => 'Notes',
        ]) ?>
        <?php if($booking->isNewRecord): ?>
            <?= $form->field($booking, 'notes')->textarea([
                'class' => 'ff-line-text-input',
                'rows'  => 4,
            ]); ?>
        <?php else: ?>
            <div id="notesIframeAjax" style="background: #F0F0F0; height: 100px; overflow-y: scroll; overflow-x: hidden; padding: 20px">
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
        <?php Box::end() ?>
    </div>
    <?php if(Yii::$app->user->identity->role != 28 && Yii::$app->user->identity->role != 27 && Yii::$app->user->identity->role != 25): ?>
    <div class="col-md-5 price-total-container js-price-total-container">
      
  <?php Box::begin([
            'header' => 'Status',
        ]) ?>

        <?= $form->field($booking, 'status')->dropDownList(BookingStatusEnum::getArrValues())->label(FALSE) ?>
        <?php //var_dump(BookingStatusEnum::getArrValues()); ?>

        <?php Box::end() ?>

  <?php Box::begin([
            'header' => 'Price',
          //  'otherContent' => Html::submitButton('Аdd a tax adjustment', [
          //        'class' => 'btn btn-primary pull-right btn-xs',
          //        'id' => 'addTaxAdjustment',
//                'class' => 'btn btn-primary pull-right js-recalculate',
         //   ]),
        ]) ?>
        <?php 
        // vardumper::dump($booking,11,1);
        ?>
    
        <div class="row">
             <div class="col-md-6"><b>Rooms</b></div>
             <div class="col-md-6 text-right" >Rooms <span id="price-text-right"><?= $booking->room_price ? $booking->room_price : 0 ?></span></div>
        </div>
        
        <div id="roomdetail">
        </div>

        <div class="row">
            <div class="col-md-9"><b>Rooms Adjustment</b></div>

            <div class="col-md-3 text-right"><?= $form->field($booking, 'adjustment_rooms')->textInput([
                    'class' => 'ff-line-text-input  text-right ',
                ])->label(FALSE) ?></div>
        </div>
        
        <div class="" id="extrasdetail-head">
            <div class="row">
                <div class="col-md-6"><b>Extras</b></div>
                <div class="col-md-6 text-right">Extras <span id="extras-text-right"><?= $booking->room_extras ? $booking->room_extras : 0 ?></span></div>
                <input type="hidden" name="extrasTextRightHidden" id="extras-text-right-hidden" value="<?= $booking->room_extras ? $booking->room_extras : 0 ?>">
            </div>
        
            <div id="extradetail">
            </div>

            <div class="row">
                <div class="col-md-9"><b>Extras Adjustment</b></div>

                <div class="col-md-3 text-right"><?= $form->field($booking, 'adjustment_extras')->textInput([
                        'class' => 'ff-line-text-input text-right ',
                    ])->label(FALSE) ?></div>
            </div>
         </div>
       
<?php 
if ( $booking->rooms[0]->room->pet_friendly)
{   $hide='';
    $hide2='';
}else{
    
    $hide= ' class="petsnodisplay" ';
    $hide2=' petsnodisplay ';
    //$hide=' style = "display:none" ';
}
?>
        
        <div class="row <?=$hide2 ?>" id="petsdetail-head">
            <div class="col-md-6"><b>Pets</b></div>
        <div class="col-md-6 text-right" >Pets $<span id="pets-text-right"><?= $booking->pets_price ? $booking->pets_price : 0 ?></span>
            
        <input type="hidden" name="petsTextRightHidden" id="pets-text-right-hidden" value="<?= $booking->pets_price ? $booking->pets_price : 0 ?>">
            
            <?php  /* $form->field($booking, 'pets_price')->textInput([
                      'class' => 'ff-line-text-input fm-price-negative text-right ',
                      'id' => 'pets-text-right',
                ])->label(FALSE)*/  ?></div>
     </div>
        
        <div id="petsdetail" <?=$hide?>>
        </div>
            
        <div class="row <?=$hide2 ?>" id="petsdetail-adjustment">
            <div class="col-md-9"><b>Pets Adjustment</b></div>
            <div class="col-md-3 text-right"><?= $form->field($booking, 'adjustment_pets')->textInput([
                    'class' => 'ff-line-text-input text-right ',
                ])->label(FALSE) ?></div>
        </div>
            
       
        <div class="row">
            <div class="col-md-9"><b>Total Adjustment</b></div>

            <div class="col-md-3 text-right"><?= $form->field($booking, 'adjustment')->textInput([
                    'class' => 'ff-line-text-input  text-right ',
                ])->label(FALSE) ?></div>
        </div>
        
        <div class="row">
        <div class="col-md-9">
            <b>Tax %</b>
        </div>
        <div class="col-md-3">
                <?= $form->field($booking, 'tax')->textInput([
                    //'class' => 'ff-line-text-input fm-number-my',
                    'class' => 'ff-line-text-input TaxInput',
                ])->label(FALSE) ?>
        </div>
     </div>
        
        <div class="row" id="adjustmentTax">
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
            <div class="col-md-12">
                <div class="row">
                    <div class="col-md-6 ">Subtotal:</div>
                    <div class="col-md-6 text-right" id="subtotal-text-right">$<?= $booking->subtotalPrice ? $booking->subtotalPrice : 0 ?></div>
                </div>
                <div class="row">
                    <div class="col-md-6 ">Tax Amount:</div>
                    <div class="col-md-6 text-right" id="tax-amount-text-right"></div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 ">TOTAL:</div>
                    <?php $total_price = $booking->totalPrice; ?>
                    <div class="col-md-6 text-right" id="total-text-right">$<?= $total_price ? $total_price : 0 ?></div>
                </div>
            </div>
        </div>
        </div>
    
     <div class="box-body">
        <div><h3>Balance: $  <span id="room_total_balance"></span><!-- room total --> </h3></div>
     </div>  
    
    <div class="box-header">
            <h3 class="box-title">Payments</h3>
        </div>
        
    <div class="box-header">
       
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
          
       <?php $totalpayment=0; if($booking->bookingPayments): ?>
                <?php foreach($booking->bookingPayments as $key => $payment): ?>
                    <tr>
                        <td style="<?= $payment->credit ? 'background: #00ff4e; color: black' : '' ?>"><?php echo $payment->amount;
                        $totalpayment+=$payment->amount ?></td>
                        <td style="<?= $payment->credit ? 'background: #00ff4e; color: black' : '' ?>"><?= BookingTypeOfPaymentEnum::getLabelName($payment->type_of_payment); ?></td>
                        <td style="<?= $payment->credit ? 'background: #00ff4e; color: black' : '' ?>"><?= date('m/d/Y H:i:s', $payment->time_of_payment); ?></td>
                        <td style="<?= $payment->credit ? 'background: #00ff4e; color: black' : '' ?>"><?= $payment->who_entered ?></td>
                        <td style="<?= $payment->credit ? 'background: #00ff4e; color: black' : '' ?>"><?= $payment->notes_entered ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <input type="hidden" id="room_total_payment"  name="totalpayment" value="<?=$totalpayment;?>">
          
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



        <?php Box::end() ?>
    <?php endif; ?>
    </div>
</div>

<?php if(Yii::$app->user->identity->role != 28 && Yii::$app->user->identity->role != 27 && Yii::$app->user->identity->role != 25): ?>
<div class="row">
    <div class="col-md-7"><!-- Extra -->
  <?php    
  ?>
        <div class="renderAjaxExtraForRoom">
        <?php
        $room_id= $roomId;
        if($booking->isNewRecord):?>
            <?php
            if($_GET['roomId']){
                $room_id = $_GET['roomId']; // From Url
                unset($_SESSION['getRoomIdFromBooking']);
            } elseif($_SESSION['getRoomIdFromBooking']){ // Not Validate
                $room_id = $_SESSION['getRoomIdFromBooking'];
                unset($_SESSION['getRoomIdFromBooking']);
            }
            $data = ExtraRoomsRecord::find()
                ->with('extra')
                ->where(['room_id' => $room_id])
                ->all();
            ?>
            <?php if($data): ?>
            <?php
            
            //надо переписать экстру на работу 1 блоком. Сейчас это 1 ajax и if/else
            //$this->render('ajax-render-extra',['data' => $data, 'roomId' => $room_id, 'daySeasonId' => $daySeasonId]);
 
            Box::begin([
                'header' => 'Extras',
            ]) ?>

                <label class="control-label">Extras for Room</label><br>

                <?php
                if($_SESSION['daySeasonId']){
                    $daySeasonId = $_SESSION['daySeasonId'];
                }
                foreach($data as $key => $value){
                    // Search not season from extra
                    $viewExtra = ExtraPrice::find()->where(['season_id' => $daySeasonId, 'extra_id' => $value->extra_id])->one();
                    if($viewExtra == null){
                        continue;
                    }
                    ?>
                    <?php if(!$_SESSION['checkBoxForBooking']): ?>
                    <div class="checkbox <?= $value->extra->is_compulsory ? 'disabled' : ""; ?>">
                        <label class="checkBox">
                            <input class="extracheckbox" type="checkbox" name="checkBoxForBooking[<?= $value->extra_id; ?>]" checked <?= $value->extra->is_compulsory ? 'disabled' : ""; ?> class="roomCheckedForBooking checked-control" data-roomCheked="<?= $value->id ?>" data-roomId="<?= $room_id ?>" data-extraId="<?= $value->extra_id ?>"/>
                            <span><?= $value->extra->name ?></span>
                        </label>
                    </div>
                    <?php else: ?>
                    <?php $returnBookingExtraId = $_SESSION['checkBoxForBooking']; ?>
                    <div class="checkbox <?= $value->extra->is_compulsory ? 'disabled' : ""; ?>">
                        <label class="checkBox">
                            <input class="extracheckbox" type="checkbox" name="checkBoxForBooking[<?= $value->extra_id; ?>]" <?= array_key_exists($value->extra_id, $returnBookingExtraId) ? 'checked' : ''; ?> <?= $value->extra->is_compulsory ? 'disabled' : ""; ?> class="roomCheckedForBooking checked-control" data-roomCheked="<?= $value->id ?>" data-roomId="<?= $room_id ?>" data-extraId="<?= $value->extra_id ?>"/>
                            <span><?= $value->extra->name ?></span>
                        </label>
                    </div>
                    <?php endif; ?>
                    <?php
                }
                ?>

            <?php Box::end() ?>
            <?php endif; ?>
        <?php else: ?>
            <?php
            $bookingExtraArr = $booking->bookingExtra;
            $returnBookingExtraId = BookingController::getBookingExtraId($bookingExtraArr);
            $data = ExtraRoomsRecord::find()
                ->with('extra')
                ->where(['room_id' => $booking->rooms[0]['room_id']])
                ->all();
            ?>
            <?php if($data): ?>
                <?php Box::begin([
                    'header' => 'Extras',
                ]) ?>

                <label class="control-label">Extras for Room</label><br>

                <?php
                /*if($_SESSION['daySeasonId']){
                    $daySeasonId = $_SESSION['daySeasonId'];
                }*/
                foreach($data as $key => $value){
                    // Search not season from extra
                    $viewExtra = ExtraPrice::find()->where(['season_id' => $booking->calendarPeriodSeason($booking->id), 'extra_id' => $value->extra_id])->one();
                    if($viewExtra == null){
                        continue;
                    }
                    ?>
                    <div class="checkbox <?= $value->extra->is_compulsory ? 'disabled' : ""; ?>">
                        <label class="checkBox">
                            <input class="extracheckbox" type="checkbox" name="checkBoxForBooking[<?= $value->extra_id; ?>]" <?= in_array($value->extra_id, $returnBookingExtraId) ? 'checked' : ''; ?> <?= $value->extra->is_compulsory ? 'disabled' : ""; ?> class="roomCheckedForBooking checked-control" data-roomCheked="<?= $value->id ?>" data-roomId="<?= $booking->rooms[0]['room_id'] ?>" data-extraId="<?= $value->extra_id ?>"/>
                            <span><?= $value->extra->name ?></span>
                        </label>
                    </div>
                    <?php
                }
                ?>

                <?php Box::end() ?>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>
    <!-- Раньше сдесь был блок Pets -->
</div>

    <?php Box::begin([
        'header' => 'Conversations',
    ]) ?>

    NOT REALISED YET
    <?php Box::end() ?>

    <input type="hidden" id="daySeasonId" name="daySeasonId" value="<?= $_SESSION['daySeasonId']; ?>">

    <?php
        if($_SESSION['daySeasonId']){
            unset($_SESSION['daySeasonId']);
        }
        if($_SESSION['getRoomIdFromBooking']){
            unset($_SESSION['getRoomIdFromBooking']);
        }
        if($_SESSION['checkBoxForBooking']){
            unset($_SESSION['checkBoxForBooking']);
        }
    ?>

    <div class="form-group">
        <?= Html::submitButton($booking->isNewRecord ? 'Create' : 'Update', ['class' => $booking->isNewRecord ? 'btn btn-success' : 'btn btn-primary', 'id' => 'ajaxBookingCreateFromMinStay']) ?>
    </div>
<?php endif; ?>
<?php ActiveForm::end() ?>


<?php Modal::begin([
    'id' => 'email-send-grid-modal',
    'header' => '<h4 class="modal-title">Email</h4>',
    'footer' => '<a href="#" class="btn btn-primary" data-dismiss="modal">Close</a>',

]); ?>

    <div class="row">
        <div class="col-lg-12"><h3 style="text-align: center">Email sent</h3></div>
    </div>

<?php Modal::end(); ?>
