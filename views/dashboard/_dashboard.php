<?php
use app\components\widget\box\Box;
use app\components\widget\grid\AdminGrid;
use app\models\booking\Booking;
use app\models\booking\BookingStatusOrSourceEnum;
use app\models\room\Room;

/** @var int $dashboardDay */
/** @var Booking $model */
//var_dump($availableRoomsDataProvider);
?>

<div class="row">
    <div class="col-md-6">
        <div class="js-check-in-box">
            
            <? Box::begin([
                'header' => 'Checking in',
            ]) ?>
            <?=
            AdminGrid::widget([
                'dataProvider' => $checkInDataProvider,
                'rowOptions' => function (Booking $model, $key, $index, $grid) {
                    return ['class' => 'grid-tr-color-status-' . BookingStatusOrSourceEnum::getLabelName($model->status_or_source)];
                },
                'columns'      => [
                    //'id',
                    [
                        'label'  => 'Room',
                        'format' => 'raw',
                        'value'  => function (Booking $model) {
                            return $model->roomsModel->title;
                        },
                    ],
                    'firstGuestName',
                    [
                        'label'  => 'Guests',
                        'format' => 'raw',
                        'value'  => function (Booking $model) {
                            //return count($model->guests);
                            return $model->bookingRooms[0]->guests_count;
                        },
                    ],
                    [
                        'label' => 'nights',
                        'value' => function (Booking $model) use ($dashboardDay) {
                            $currentStayFor = $model->getStayPeriodByDay($dashboardDay);
                            if ($currentStayFor === 0) {
                                return 'only one day';
                            } else {
                                return $currentStayFor . ($currentStayFor == 1 ? ' night' : ' nights');
                            }
                        },
                    ],
                
                
                ],
            ]); ?>
            <? Box::end() ?>
        
        </div>
        <div class="js-check-out-box">
            <? Box::begin([
                'header' => 'Checking out',
            ]) ?>
            <?= AdminGrid::widget([
                'dataProvider' => $checkOutDataProvider,
                'rowOptions' => function (Booking $model, $key, $index, $grid) {
                    return ['class' => 'grid-tr-color-status-' . BookingStatusOrSourceEnum::getLabelName($model->status_or_source)];
                },
                'columns'      => [
                    //'id',
                    [
                        'label'  => 'Room',
                        'format' => 'raw',
                        'value'  => function (Booking $model) {
                            return $model->roomsModel->title;
                        },
                    ],
                    'firstGuestName',
                    [
                        'label'  => 'Guests',
                        'format' => 'raw',
                        'value'  => function (Booking $model) {
                            //return count($model->guests);
                            return $model->bookingRooms[0]->guests_count;
                        },
                    ],
                    [
                        'label' => 'nights',
                        'value' => function (Booking $model) use ($dashboardDay) {
                            $currentStayFor = $model->getStayPeriodByDay($dashboardDay);
                            if ($currentStayFor === 0) {
                                return 'only one day';
                            } else {
                                return $currentStayFor . ($currentStayFor == 1 ? ' night' : ' nights');
                            }
                        },
                    ],
                
                ],
            ]); ?>
            <? Box::end() ?>
        </div>

        <div class="js-check-out-box">
        <? Box::begin([
            'header' => 'Checking in/out at the same day',
        ]) ?>

        <?= AdminGrid::widget([
            'dataProvider' => $checkInDataProviderSameDay,
            'rowOptions' => function (Booking $model, $key, $index, $grid) {
                return ['class' => 'grid-tr-color-status-' . BookingStatusOrSourceEnum::getLabelName($model->status_or_source)];
            },
            'columns'      => [
                //'id',
                [
                    'label'  => 'Room',
                    'format' => 'raw',
                    'value'  => function (Booking $model) {
                        return $model->roomsModel->title;
                    },
                ],
                'firstGuestName',
                [
                    'label'  => 'Guests',
                    'format' => 'raw',
                    'value'  => function (Booking $model) {
                        //return count($model->guests);
                        return $model->bookingRooms[0]->guests_count;
                    },
                ],
                [
                    'label' => 'nights',
                    'value' => function (Booking $model) use ($dashboardDay) {
                        return $model->getPeriodFromAndTo($model->bookingRooms[0]->period_id);
                    },
                ],

            ],
        ]); ?>

        <? Box::end() ?>
        </div>

        <div class="js-check-out-box">
            <? Box::begin([
                'header' => 'Made today reservation',
            ]) ?>

            <?= AdminGrid::widget([
                'dataProvider' => $checkInDataProviderToDayReservation,
                'rowOptions' => function (Booking $model, $key, $index, $grid) {
                    return ['class' => 'grid-tr-color-status-' . BookingStatusOrSourceEnum::getLabelName($model->status_or_source)];
                },
                'columns'      => [
                    //'id',
                    [
                        'label'  => 'Room',
                        'format' => 'raw',
                        'value'  => function (Booking $model) {
                            return $model->roomsModel->title;
                        },
                    ],
                    'firstGuestName',
                    [
                        'label'  => 'Guests',
                        'format' => 'raw',
                        'value'  => function (Booking $model) {
                            //return count($model->guests);
                            return $model->bookingRooms[0]->guests_count;
                        },
                    ],
                    [
                        'label' => 'nights',
                        'value' => function (Booking $model) use ($dashboardDay) {
                            return $model->getPeriodFromAndTo($model->bookingRooms[0]->period_id);
                        },
                    ],

                ],
            ]); ?>

            <? Box::end() ?>
        </div>

    </div>
    <div class="col-md-6">
        <div class="js-available-rooms-box">
            <? Box::begin([
                'header' => 'Available rooms',
            ]) ?>
            <div class="rooms-progress-bar">
                <div class="row">
                    <div class="col-md-8">
                        <div class="progress-label">Available rooms</div>
                    </div>
                    <div class="col-md-4 text-right"><?= $roomCountLabel ?></div>
                </div>
                
                <div class="outer-progress">
                    <div class="inner-progress" style="width: <?= $availableRoomsRelative ?>%">
                    
                    </div>
                </div>
            </div>
            <?= AdminGrid::widget([
                'dataProvider' => $availableRoomsDataProvider,
                'columns'      => [
                    [
                        'attribute' => 'title',
                        'label'     => 'Address',
                        'value' => function ($model) {
                            if($model->title){
                                return mb_strimwidth($model->title, 0, 40, "...");
                            }
                        },
                    ],
                    [
                        'attribute' => 'freeUntilDate',
                        'label'     => 'Free until',
                    ],
                    [
                        
                        'label' => 'Price',
                        'value' => function (Room $model) use ($dashboardDay) {
                            $price = $model->getDayPriceByDay($dashboardDay);
                            return $price ? ('$' . $price) : 'no price';
                        },
                    ],
                    [
                        'label'  => '',
                        'format' => 'raw',
                        'value'  => function (Room $model) {
                            return \yii\helpers\Html::a('New booking', ['/booking/create', 'roomId' => $model->id]);
                        },
                    ],
                ],
            
            ]); ?>
            <? Box::end() ?>
        </div>
    </div>
</div>

<?php
/*'rowOptions' => function ($model, $key, $index, $grid) {
    return ['class' => 'test'];
}*/
?>