<?php
use app\components\AdminActionColumns;
use app\components\AdminActionColumnsForBookingList;
use app\components\widget\box\Box;
use app\components\widget\grid\AdminGrid;
use app\helpers\TimestampHelper;
use app\models\booking\Booking;
use app\models\booking\BookingStatusEnum;
use yii\bootstrap\Modal;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

?>
<div class="loader loaderInBookingEmail" style="z-index: 1000; top: 50%; left: 50%; position: absolute; display: none"></div>
<?php
if(Yii::$app->user->identity->role == 28 || Yii::$app->user->identity->role == 27 || Yii::$app->user->identity->role == 25){
    $but = '';
} else {
    $but = [
        'url'   => \yii\helpers\Url::toRoute(['create']),
        'value' => 'Add New Booking',
    ];
}
?>

<?php Box::begin([
    'header'    => 'Create Booking',
    'addButton' => $but,
]) ?>

<?= AdminGrid::widget([
    'dataProvider' => $dataProvider,
    'columns'      => [
        'id',
        //'notes:raw',
        [
            'label'  => 'Date',
            'format' => 'raw',
            'value'  => function (Booking $model) {
                $bookingRoomsModel = (new Query())->select(['period_id'])->from('vr_booking_rooms')->where(['booking_id' => $model->id])->one();
                if($bookingRoomsModel['period_id']){
                    $calendarPeriodModel = (new Query())->select(['from','to'])->from('vr_calendar_period')->where(['id' => $bookingRoomsModel['period_id']])->one();
                    if($calendarPeriodModel){
                        $arrival_date = TimestampHelper::dayToDateStringMail($calendarPeriodModel['from']) . " - " . TimestampHelper::dayToDateStringMail($calendarPeriodModel['to']);
                        return $arrival_date;
                    }
                }
            },
        ],
        [
            'label'  => 'Unit',
            'format' => 'raw',
            'value'  => function (Booking $model) {
                $bookingRoomsModel = (new Query())->select(['room_id'])->from('vr_booking_rooms')->where(['booking_id' => $model->id])->one();
                if($bookingRoomsModel['room_id']){
                    $roomModel = (new Query())->select(['title'])->from('vr_room')->where(['id' => $bookingRoomsModel['room_id']])->one();
                    if($roomModel){
                        return mb_strimwidth($roomModel['title'], 0, 60, "...");
                    }
                }
            },
        ],
        [
            'label'  => 'Guests',
            'format' => 'raw',
            'value'  => function (Booking $model) {
                $guestId = $model->guests[0]['guest_id'];
                $guestModel = (new Query())->select(['*'])->from('vr_guest')->where(['id' => $guestId])->one();
                if($guestModel){
                    $guestModel['full_name'] ? $full_name = $guestModel['full_name'] : $full_name = "";
                    $guestModel['last_name'] ? $last_name = $guestModel['last_name'] : $last_name = "";
                    return mb_strimwidth($full_name, 0, 40, "...") . "  " . mb_strimwidth($last_name, 0, 40, "...");
                } else {
                    return "Not Guest";
                }

                //return implode("<br>", ArrayHelper::map($model->getGuests(), 'id', 'full_name'));
            },
        ],
        [
            'attribute' => 'status',
            'format'    => 'raw',
            'value'     => function (Booking $model) {
                return Html::tag('span', $model->getStatusTitle(), [
                    'class' => 'label label-' . BookingStatusEnum::getLabelName($model->status),
                ]);
            },
        ],
        [
            'label'  => 'Confirmation email',
            'format' => 'raw',
            'contentOptions' => ['class' => 'grid-email-booking'],
            'value'=> function($data){
                //return '<a href="#" class="re-send-email-booking" data-booking="'.$data->id.'">Resend email</a>';
                return '<input type="hidden" name="booking-id" class="re-send-email-booking" value="'.$data->id.'">
                            <input type="submit" class="btn btn-info re-send-email-booking" value="Resend email">';
            },
        ],
        [
            //'class' => AdminActionColumns::className(),
            'class' => AdminActionColumnsForBookingList::className(),
        ],
    ],
]); ?>


<?
Box::end()
?>


<?php Modal::begin([
    'id' => 'email-send-grid-modal',
    'header' => '<h4 class="modal-title">Email</h4>',
    'footer' => '<a href="#" class="btn btn-primary" data-dismiss="modal">Close</a>',

]); ?>

    <div class="row">
        <div class="col-lg-12"><h3 style="text-align: center">Email sent</h3></div>
    </div>


<?php Modal::end(); ?>