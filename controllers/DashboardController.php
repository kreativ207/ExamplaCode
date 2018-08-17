<?php
/**
 * Created by PhpStorm.
 * User: PC
 * Date: 24.09.2016
 * Time: 14:59
 */

namespace app\controllers;


use app\helpers\TimestampHelper;
use app\models\booking\Booking;
use app\models\calendar\CalendarPeriod;
use app\models\calendar\PeriodTypeEnum;
use app\models\room\Room;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;

class DashboardController extends AdminController
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['login', 'error'],
                        'roles' => ['?'],
                    ],
                    [
                        'allow' => true,
                        'roles' => ['admin', 'moder', 'user'],
                    ],
                ],
            ],

        ];
    }

    public function actionIndex()
    {

        $date = \Yii::$app->request->post('date');
        if (!$date) {
            $dashboardDay = TimestampHelper::currentDay();
        } else {
            $dashboardDay = TimestampHelper::dateStringToDays($date);
        }

        $checkInDataProvider = $this->getCheckInDataProvider($dashboardDay);
        $checkOutDataProvider = $this->getCheckOutDataProvider($dashboardDay);
        $checkInDataProviderSameDay = $this->getCheckInDataProviderSameDay($dashboardDay);
        $checkInDataProviderToDayReservation = $this->getCheckInDataProviderToDayReservation($dashboardDay);
        $availableRoomsDataProvider = $this->getAvailableRoomsDataProvider($dashboardDay);

        $totalRoomsCount = Room::find()->count();
        $availableRoomsCount = $availableRoomsDataProvider->count;
        $roomCountLabel = $availableRoomsCount . '/' . $totalRoomsCount;

        if($totalRoomsCount != 0){
            $availableRoomsRelative = $availableRoomsCount * 100 / $totalRoomsCount;
        } else {
            $availableRoomsRelative = 0;
        }

        //var_dump($roomCountLabel);exit;



        if (\Yii::$app->request->isAjax) {
            return $this->renderPartial('_dashboard', [
                'checkInDataProvider'        => $checkInDataProvider,
                'checkOutDataProvider'       => $checkOutDataProvider,
                'checkInDataProviderSameDay' => $checkInDataProviderSameDay,
                'availableRoomsDataProvider' => $availableRoomsDataProvider,
                'checkInDataProviderToDayReservation' => $checkInDataProviderToDayReservation,
                'roomCountLabel'             => $roomCountLabel,
                'availableRoomsRelative'     => $availableRoomsRelative,
                'dashboardDay'               => $dashboardDay,
            ]);
        } else {
            return $this->render('index', [
                'checkInDataProvider'        => $checkInDataProvider,
                'checkOutDataProvider'       => $checkOutDataProvider,
                'checkInDataProviderSameDay' => $checkInDataProviderSameDay,
                'availableRoomsDataProvider' => $availableRoomsDataProvider,
                'checkInDataProviderToDayReservation' => $checkInDataProviderToDayReservation,
                'roomCountLabel'             => $roomCountLabel,
                'availableRoomsRelative'     => $availableRoomsRelative,
                'dashboardDay'               => $dashboardDay,
            ]);
        }
    }

    /**
     * @param $currentDay
     *
     * @return ActiveDataProvider
     */
    private function getCheckInDataProviderSameDay($currentDay)
    {
        $itemsFrom = CalendarPeriod::find()->where(['item_type' => PeriodTypeEnum::BOOKING_ROOM])
            ->andWhere(['to' => $currentDay])->orderBy('item_root_id DESC')->all();
        $res = [];
        foreach($itemsFrom as $key => $val){
            $itemsTo = CalendarPeriod::find()->where(['item_type' => PeriodTypeEnum::BOOKING_ROOM])
                ->andWhere(['from' => $currentDay + 1])->andWhere(['item_id' => $val->item_id])->one();
            if($itemsTo){
                //$res[$val->item_root_id] = $val->item_root_id;
                $res[$itemsTo->item_root_id] = $itemsTo->item_root_id;
            }
        }
        $checkInDataProvider = new ActiveDataProvider([
            'query' => Booking::find()->andWhere(['id' => $res]),
        ]);
        return $checkInDataProvider;
    }
    
    /**
     * @param $currentDay
     *
     * @return ActiveDataProvider
     */
    private function getCheckInDataProviderToDayReservation($currentDay)
    {
        $checkInDataProvider = new ActiveDataProvider([
            'query' => Booking::find()->andWhere(['time_save' => $currentDay]),
        ]);
        return $checkInDataProvider;
    }


    /**
     * @param $currentDay
     *
     * @return ActiveDataProvider
     */
    private function getCheckInDataProvider($currentDay)
    {
        $items = CalendarPeriod::find()->where(['item_type' => PeriodTypeEnum::BOOKING_ROOM])
            ->andWhere(['from' => $currentDay,])->all();
        $ids = ArrayHelper::map($items, 'item_root_id', 'item_root_id');
        $checkInDataProvider = new ActiveDataProvider([
            'query' => Booking::find()->andWhere(['id' => $ids]),
        ]);
        return $checkInDataProvider;
    }
    
    private function getCheckOutDataProvider($currentDay)
    {
        $items = CalendarPeriod::find()->where(['item_type' => PeriodTypeEnum::BOOKING_ROOM])
            ->andWhere(['to' => $currentDay,])->all();
        $ids = ArrayHelper::map($items, 'item_root_id', 'item_root_id');
        $checkInDataProvider = new ActiveDataProvider([
            'query' => Booking::find()->andWhere(['id' => $ids]),
        ]);
        return $checkInDataProvider;
    }
    
    /**
     * @param $dashboardDay
     *
     * @return ActiveDataProvider
     */
    private function getAvailableRoomsDataProvider($dashboardDay)
    {
        $notAvailableRoomsPeriods = CalendarPeriod::find()
            ->where(['item_type' => [PeriodTypeEnum::BOOKING_ROOM, PeriodTypeEnum::BOOKING_BLOCK],])
            ->andWhere(['<=', 'from', $dashboardDay])
            ->andWhere(['>=', 'to', $dashboardDay])->all();
        $notAvailableRoomIds = ArrayHelper::map($notAvailableRoomsPeriods, 'item_id', 'item_id');
        $availableRoomsDataProvider = new ActiveDataProvider([
            'query' => Room::find()->where(['user_id' => Yii::$app->user->identity->getId()]),/*Room::find()->where(['not in', 'id', array_values($notAvailableRoomIds)]),*/
        ]);
        return $availableRoomsDataProvider;
    }
}