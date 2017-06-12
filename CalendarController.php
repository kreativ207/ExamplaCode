<?php

namespace app\controllers;
use app\models\booking\BookingBlock;
use Yii;

use app\helpers\TimestampHelper;
use app\models\booking\Booking;
use app\models\booking\BookingRoom;
use app\models\booking\BookingStatusOrSourceEnum;
use app\models\calendar\CalendarPeriod;
use app\models\calendar\PeriodTypeEnum;
use app\models\room\Room;
use app\models\settings\WidgetSettings;
use app\models\settings\WidgetRooms;
use yii\filters\AccessControl;
use yii\db\Query;

class CalendarController extends AdminController
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['booking','login', 'error'],
                        'roles' => ['?'],
                    ],
                    [
                        'allow' => true,
                        'roles' => ['admin', 'moder', 'reservationist', 'housekeeper_manager'],
                    ],
                ],
            ],

        ];
    }

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionBooking()
    {
   
        $dateMap = [];

        $startDate = intval(\Yii::$app->request->get('startDate'));
        $endDate = intval(\Yii::$app->request->get('endDate'));
        
      
        if (!$startDate || !$endDate) {
            $startDate = mktime(null, null, null, date('m'), 1, date('Y'));
            $endDate = mktime(null, null, null, date('m') + 1, 1, date('Y'));
            $shift=0;
        }else
        {   $shift=1;
        }
        
        $currentTime = time();
        if ($currentTime < $startDate || $currentTime > $endDate) {
            $currentDate = TimestampHelper::timeToDays($startDate);
        } else {
            $currentDate = TimestampHelper::timeToDays($currentTime);
        }

        
        for ($i = $startDate; $i < $endDate; $i += TimestampHelper::DAY_SECS) {
            $dayNum = date('d', $i);
            $dayName = date('D', $i);
            $dateMap[] = [
                'dayNum'    => ltrim($dayNum, '0'),
                'dayName'   => $dayName,
                'dateIndex' => TimestampHelper::timeToDays($i)-$shift,
            ];
        }

        $room_id = false;
 
        if(strstr($_SERVER['HTTP_REFERER'],'rooms/update?id='))
        {
            $room_id = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY); // id=31
            $room_id = str_replace('id=','',$room_id); 
        }
   
        if(strstr($_SERVER['HTTP_REFERER'],'widget'))
        {
            $url = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY);
            $url = str_replace('w=','',$url);
        }
        $rooms = $this->getCalendarRooms($startDate, $endDate, $url, $room_id);


        return json_encode([
            'rooms'      => $rooms,
            'dateMap'    => $dateMap,
            'periodInfo' => date('m-d-Y', $startDate) . ' - ' . date('m-d-Y', $endDate),
            'period'     => [
                'start'   => TimestampHelper::timeToDays($startDate),
                'current' => $currentDate,
                'end'     => TimestampHelper::timeToDays($endDate) - 1,
            ],

        ]);
    }

    /**
     * @param $startDate
     * @param $endDate
     * @return array
     */
    public function getCalendarRooms($startDate, $endDate,$widget='',$room_id = false)
    {
        $rooms = [];
        /** @var Room[] $roomRecords */

        if($widget)
        {
            //Yii::warning($widget,'wr');

            $widgetSettings = (new Query())->select(['*'])->from('vr_widget_settings')->where(['w_uniqid' => $widget])->one();
            $widgetRooms = WidgetRooms::find()->where(['widget_id' => $widgetSettings['id']])->asArray()->all();
            $roomId=[];

            foreach ($widgetRooms as $widgetRoom)
            {
                $roomId[]=$widgetRoom['room_id'];
            }

            $roomRecords = Room::find()->Where(['id' => $roomId])->andWhere(['active_room' => 1])->orderBy(['sortable' => SORT_ASC])->all();
            //Yii::warning(count($roomRecords) ,'RR');

        } elseif ($room_id){
            $roomRecords = Room::find()->andWhere(['active_room' => 1])->andWhere(['id' => $room_id])->orderBy(['sortable' => SORT_ASC])->all();
        }  else {
            $roomRecords = Room::find()->andWhere(['active_room' => 1])->orderBy(['sortable' => SORT_ASC])->all();
        }
        // Yii::warning($roomRecords ,'RR2');

        foreach ($roomRecords as $roomRecord) {
            $blocks = [];

            $startDay = TimestampHelper::timeToDays($startDate);
            $endDay = TimestampHelper::timeToDays($endDate);

            $blockPeriods = CalendarPeriod::find()
                ->where(['item_type' => PeriodTypeEnum::BOOKING_BLOCK])
                ->andWhere(['item_id' => $roomRecord->id,])
                ->andWhere(['>=', 'from', $startDay])
                ->andWhere(['<=', 'to', $endDay])
                ->all();

//            $blocksIds = ArrayHelper::map($blockPeriods, 'id', 'item_root_id');
//
//            $blocksRecords = BookingBlock::findAll(['id' => $blocksIds]);

            // tomake Подтягивать все периоды для одной блокировки
            foreach ($blockPeriods as $blockPeriod) {
                $allBookingBlock = BookingBlock::find()->where(['id' => $blockPeriod->item_root_id])->one();
                $blocks[] = [
                    'id'   => $blockPeriod->id,
                    'block_id' => $blockPeriod->item_root_id,
                    'title' => $allBookingBlock->title,
                    'description' => $allBookingBlock->description,
                    'from' => $blockPeriod->from,
                    'to'   => $blockPeriod->to,
                    'tooltip' => "Title: <br> $allBookingBlock->title<br>Description:<br> $allBookingBlock->description",
                ];
            }


            $room = [];
            /** @var BookingRoom[] $roomBookings */
            $room['id'] = $roomRecord->id;
            $room['title'] = mb_strimwidth($roomRecord->title, 0, 30, "...");
            $room['blocks'] = $blocks;
            $room['bookings'] = [];

            //Yii::warning($room ,'room');
            //Yii::warning($blocks ,'$blocks');

            $roomBookings = BookingRoom::findByDateRange($startDate, $endDate, $roomRecord->id);
            if ($roomBookings) {
                foreach ($roomBookings as $roomBooking) {

                    // Yii::warning($roomBooking,'rb');

                    if(empty($widget))
                    {   $guests = $roomBooking->booking->findGuests();
                    }

                    $bookingName = $guests ? $guests[0]->full_name : 'No Name';
                    $bookingSecondName = $guests ? $guests[0]->last_name : '';
                    $phone = json_decode($guests[0]->phones);
                    $bookingPhones = '';
                    //file_put_contents('testCalendarPhone.txt',$guests[0]->phones, FILE_APPEND);
                    if($phone){
                        foreach ($phone as $key){
                            $bookingPhones .= $key->type . " : " . $key->tel . "<br />";
                        }
                    }


                    //$bookingPhones = $guests ? implode(', ', $guests[0]->getPhones()) : 'No Phone'; // Нужно исправить
                    $in = $roomBooking->getFromDate();
                    $out = $roomBooking->getToDate();
//                    $in = $roomBooking->getFrom();
//                    $out = $roomBooking->getTo();
                    $price = '$' . $roomBooking->booking->getTotalPrice();
                    $addMessage = '';
                    $roomsCount = count($roomBooking->booking->bookingRooms);
                    if ($roomsCount >= 2) {
                        $addMessage .= "(for $addMessage rooms)";
                    }
                    //'grid-tr-color-status-' . BookingStatusOrSourceEnum::getLabelName($model->status_or_source)]
                    //$status_or_source

                    /** @var Booking $bookingModel */
                    $bookingModel = Booking::find()->where(['id' => $roomBooking->booking_id])->one();
                    $status = $bookingModel->status_or_source;
                    if($status){
                        $status_or_source = "grid-tr-color-status-" . BookingStatusOrSourceEnum::getLabelName($status);
                    }

                    //not_full_amount_payment
                    if($bookingModel->tax){
                        $full_price_not_tax = $bookingModel->room_price + $bookingModel->room_extras;
                        $full_amount_payment = ($full_price_not_tax * $bookingModel->tax / 100) + $full_price_not_tax;
                    } else {
                        $full_amount_payment = $bookingModel->room_price + $bookingModel->room_extras;
                    }

                    $bookingPayments = $bookingModel->bookingPayments;
                    $price_all = null;
                    if($bookingPayments){
                        foreach ($bookingPayments as $key => $val){
                            if($val['credit']){
                                continue;
                            } else {
                                $price_all += $val->amount;
                            }
                        }
                    }
                    $price_all = $full_amount_payment - $price_all;

                    /*$priceAll = $bookingModel->full_amount_payment - $full_amount_payment;
                    if($priceAll !== 0){
                        //$status_or_source .= " not_full_amount_payment";
                    }*/

                    if(strstr($_SERVER['HTTP_REFERER'],'widget'))
                    {   $tooltip=''; //clear tooltip for widjet calls
                        $bookingName='Reserved';
                        $status_or_source="grid-tr-color-status-green";
                        $price_all=0;
                        $bookingSecondName='';
                    }else
                    {
                        $bookingName = mb_strimwidth($bookingName, 0, 40, "...");
                        $bookingSecondName = mb_strimwidth($bookingSecondName, 0, 40, "...");
                        $tooltip="Name: $bookingName $bookingSecondName<br>Phone:<br>$bookingPhones<br><br>In: $in<br>Out: $out<br><br>Price: $price $addMessage";
                    }

                    $room['bookings'][] = [
                        'id'        => $roomBooking->id,
                        'bookingId' => $roomBooking->booking_id,
                        'status_or_source' => $status_or_source,
                        'full_amount_payment' => round($full_amount_payment),
                        'price_all' => $price_all,
                        'name'      => mb_strimwidth($bookingName, 0, 40, "..."),
                        'second'    => mb_strimwidth($bookingSecondName, 0, 40, "..."),
                        'checkIn'   => $roomBooking->getFrom(),
                        'checkOut'  => $roomBooking->getTo(),
                        'tooltip'   => $tooltip,
                    ];
                }
            }

            $rooms[] = $room;
        }
        return $rooms;
    }


}