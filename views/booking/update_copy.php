<?
use app\components\widget\box\Box;
use app\models\booking\Booking;
use app\models\guest\Guest;
use app\models\room\Room;
use kartik\form\ActiveForm;
use yii\helpers\Html;

/** @var Booking $booking */
$this->title = 'Booking #' . $booking->id;
?>
    <div class="row">
        <div class="text-right booking-status col-md-12">Status: <?= $booking->statusTitle ?></div>
    </div>
<? // if ($errors): ?>
    <!--    --><? // foreach ($errors as $error): ?>
    <!--        <div class="alert alert-danger">-->
    <!--            <strong>Error!</strong> --><? //= $error ?>
    <!--        </div>-->
    <!--    --><? // endforeach; ?>
<? // endif; ?>
<? $form = ActiveForm::begin(); ?>
    <div class="row">
        <div class="col-md-7">

            <div class="js-multiply-inputs">
                <? Box::begin([
                    'header'       => 'Booking detail',
                    'otherContent' => Html::a('+ Add Room', '#', [
                        'class' => 'btn btn-default pull-right js-multiply-add',
                    ]),
                ]) ?>



<?
                $guestsCountDropDown = [];
                for ($i = 1; $i <= 15; $i++) {
                    $guestsCountDropDown[$i] = $i . ' ' . ($i == 1 ? 'guest' : 'guests');
                }

                ?>
                <?= $form->field($model, 'rooms')->widget(\app\widgets\MultiplyInput::className(), [
                    'itemAttributeNames' => [
                        'from',
                        'to',
                    ],
                    'itemContent'        => '

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="">
                                            <label class="control-label">Check
                                                in:</label>
                                            <input name-template="rooms[{index}][check_in]" type="text"
                                                   value="{value[from]}"
                                                   class="form-control fm-date">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="">
                                            <label class="control-label"
                                            >Out:</label>
                                            <input name-template="rooms[{index}][check_out]" type="text"
                                                   value="{value[to]}"
                                                   class="form-control fm-date">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <a href="#" class="pull-right delete-input-item js-multiply-remove"><i
                                                class="fa fa-times"></i>Delete Room</a>
                                    </div>
                                </div>

                                <div>Room:</div>
                                <div class="row">
                                    <div class="col-md-6">' .
                        Html::dropDownList('', 1, $availableRoomsMap, [
                            'name-template' => '',
                        ]) . '
                                    </div>
                                    <div class="col-md-6">' .
                        Html::dropDownList('', 1, $guestsCountDropDown, [
                            'name-template' => '',
                        ]) . '
                                    </div>
                                </div>
                 
                ',
                ]) ?>

                <div class="js-multiply-wrapper">
                    <? if ($booking->getRooms()): ?>
                        <? foreach ($booking->getRooms() as $bookingRoom): ?>

                        <? endforeach; ?>
                    <? else: ?>
                        <div class="form-group js-multiply-target">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="">
                                        <label class="control-label">Check
                                            in:</label>
                                        <input name-template="rooms[{index}][check_in]" type="text"
                                               class="form-control fm-date">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="">
                                        <label class="control-label"
                                        >Out:</label>
                                        <input name-template="rooms[{index}][check_out]" type="text"
                                               class="form-control fm-date">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <a href="#" class="pull-right delete-input-item js-multiply-remove"><i
                                            class="fa fa-times"></i>Delete Room</a>
                                </div>
                            </div>

                            <div>Room:</div>
                            <div class="row">
                                <div class="col-md-6">
                                    <select name-template="rooms[{index}][room_id]"
                                            class="form-control">
                                        <? /** @var Room[] $availableRoomsMap */ ?>
                                        <option data-default
                                                value="">Select room...
                                        </option>
                                        <? foreach ($availableRoomsMap as $room): ?>
                                            <option value="<?= $room['id'] ?>"><?= $room['title'] ?></option>
                                        <? endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <select name-template="rooms[{index}][guests_count]"
                                            class="form-control">
                                        <option data-default
                                                value="">Select guests count...
                                        </option>
                                        <? for ($i = 1; $i <= 10; $i++): ?>
                                            <option value="<?= $i ?>"><?= $i ?> <?= $i == 1 ? 'guest' : 'guests' ?>
                                            </option>
                                        <? endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <? endif; ?>

                </div>
                <? Box::end() ?>
            </div>
            <div class="js-multiply-inputs js-select-guests">
                <? Box::begin([
                    'header'       => 'Guests',
                    'otherContent' => Html::a('+ Add Guest', '#', [
                        'class' => 'btn btn-default pull-right js-multiply-add',
                    ]),
                ]) ?>
                <!--            --><? //= $form->field($booking, 'check_in')->widget(DatePicker::className()) ?>
                <!--            --><? //= $form->field($booking, 'check_out')->widget(DatePicker::className()) ?>

                <div class="js-multiply-wrapper">
                    <? if ($booking->bookingGuests): ?>
                        <? foreach ($booking->bookingGuests as $bookingGuest): ?>
                            <div class="form-group js-multiply-target">
                                <div class="row">
                                    <? /** @var Guest[] $guests */ ?>
                                    <div class="col-md-4">
                                        <select name-template="guests[]"
                                                class="form-control js-select-guest">
                                            <option value="">Select guest...</option>
                                            <? foreach ($guests as $guest): ?>
                                                <option <?= $bookingGuest->guest_id == $guest->id ? 'selected' : '' ?>
                                                    value="<?= $guest->id ?>"><?= $guest->full_name ?></option>
                                            <? endforeach; ?>

                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="js-guest-info">
                                            <? foreach ($guests as $guest): ?>
                                                <div class="js-multiply-hide guest-info"
                                                     id="guest-info-<?= $guest->id ?>"
                                                    <?= $bookingGuest->guest_id == $guest->id ? '' : 'style="display: none;"' ?> ><?= $guest->getShortInfo() ?></div>
                                            <? endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <a href="#" class="pull-right delete-input-item js-multiply-remove"><i
                                                class="fa fa-times"></i>Delete Guest</a>
                                    </div>
                                </div>

                            </div>
                        <? endforeach; ?>
                    <? else: ?>
                        <div class="form-group js-multiply-target">
                            <div class="row">
                                <? /** @var Guest[] $guests */ ?>
                                <div class="col-md-4">
                                    <select name-template="guests[]"
                                            class="form-control js-select-guest">
                                        <option data-default
                                                value="">Select guest...
                                        </option>
                                        <? foreach ($guests as $guest): ?>
                                            <option value="<?= $guest->id ?>"><?= $guest->full_name ?></option>
                                        <? endforeach; ?>

                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <div class="js-guest-info">
                                        <? foreach ($guests as $guest): ?>
                                            <div class="js-multiply-hide guest-info" id="guest-info-<?= $guest->id ?>"
                                                 style="display: none;"><?= $guest->getShortInfo() ?></div>
                                        <? endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <a href="#" class="pull-right delete-input-item js-multiply-remove"><i
                                            class="fa fa-times"></i>Delete Guest</a>
                                </div>
                            </div>

                        </div>
                    <? endif; ?>

                </div>
                <? Box::end() ?>
            </div>

            <? Box::begin([
                'header' => 'Notes',
            ]) ?>
            <?= $form->field($booking, 'notes')->textarea([
                'class' => 'ff-line-text-input',
                'rows'  => 1,
            ]); ?>
            <? Box::end() ?>
        </div>
        <div class="col-md-5 price-total-container">
            <? Box::begin([
                'header'       => 'Money',
                'otherContent' => Html::submitButton('Recalculate', [
                    'class' => 'btn btn-primary pull-right js-recalculate',
                ]),
            ]) ?>
            <div class="row">
                <div class="col-md-9">Rooms</div>
                <? /** @var integer $roomsPrice */ ?>
                <div class="col-md-3 text-right">$<?= $roomsPrice ? $roomsPrice : '0' ?></div>
            </div>
            <div class="row">
                <div class="col-md-9">Extras</div>
                <? /** @var integer $extrasPrice */ ?>
                <div class="col-md-3 text-right">$<?= $extrasPrice ? $extrasPrice : '0' ?></div>
            </div>
            <div class="row">
                <div class="col-md-9">Adjustment</div>
                <? /** @var integer $extrasPrice */ ?>
                <div class="col-md-3 text-right"><?= $form->field($booking, 'adjustment')->textInput([
                        'class' => 'ff-line-text-input fm-price-negative text-right ',
                    ])->label(false) ?></div>
            </div>

            <?= $form->field($booking, 'adjustment_description')->textInput([
                'class'       => 'ff-line-text-input',
                'placeholder' => 'Description',
            ])->label(false) ?>
            <div class="row">
                <div class="col-md-3">
                    <?= $form->field($booking, 'tax')->textInput([
                        'class' => 'ff-line-text-input fm-number',
                    ]) ?>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <div class="col-md-8 text-right">Subtotal:</div>
                        <? /** @var integer $subtotalPrice */ ?>
                        <div class="col-md-4 text-right"><?= $subtotalPrice ? $subtotalPrice : '0' ?>$</div>
                    </div>

                    <div class="row">
                        <div class="col-md-8 text-right">TOTAL:</div>
                        <? /** @var integer $totalPrice */ ?>
                        <div class="col-md-4 text-right"><?= $totalPrice ? $totalPrice : '0' ?>$</div>
                    </div>
                </div>
            </div>

            <? Box::end() ?>

            <? Box::begin([
                'header' => 'Payments',
            ]) ?>
            NOT REALISED YET
            <? Box::end() ?>
        </div>
    </div>
<? Box::begin([
    'header' => 'Extras',
]) ?>
    NOT REALISED YET
<? Box::end() ?>

<? Box::begin([
    'header' => 'Conversations',
]) ?>
    NOT REALISED YET
<? Box::end() ?>
    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Create' : 'Update', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>
<? ActiveForm::end() ?>