<?php


namespace app\controllers;


use app\helpers\TimestampHelper;
use app\models\booking\Booking;
use app\models\booking\BookingBlock;
use app\models\booking\BookingExtra;
use app\models\booking\BookingNotes;
use app\models\booking\BookingPayment;
use app\models\booking\BookingRoom;
use app\models\booking\BookingStatusOrSourceEnum;
use app\models\booking\enums\BookingStatusEnum;
use app\models\calendar\CalendarPeriod;
use app\models\calendar\PeriodTypeEnum;
use app\models\guest\Guest;
use app\models\room\ExtraRoomsRecord;
use app\models\room\LinkRooms;
use app\models\room\Room;
use app\models\room\PriceViewTypeExtraEnum;
use app\models\room\RoomPrice;
use app\models\season\Tax;
use app\models\settings\EmailsLetters;
use app\models\settings\EmailsSettings;
use app\models\settings\EmailsSmtpSettings;
use app\services\BookingService;
use app\services\EmailSendService;
use app\services\UsersServices;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Stripe;
use Yii;
use yii\data\ActiveDataProvider;
use AuthorizeNetAIM;
use yii\db\Query;
use yii\filters\AccessControl;
use yii\swiftmailer\Mailer;
use yii\web\HttpException;

class BookingController extends AdminController
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
                        'roles' => ['admin', 'moder', 'bookkeeper', 'reservationist', 'housekeeper_manager', 'housekeeper', 'vendor'],
                    ],
                ],
            ],

        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {       
            $this->enableCsrfValidation = false;

        return parent::beforeAction($action);
    }
    
    public function actionIndex()
    {   
        $dataProvider = new ActiveDataProvider([
            'query' => Booking::find(),
        ]);
        
        $dataProvider->pagination = false;
        
        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionBookingMinimalDaysYes(){
        Yii::info('BookingMinimalDaysYes','security_log');
        $session = Yii::$app->session;
        $session->set('BookingMinimalDaysYes', 1);
        return 1;
    }

    public function saveBookingExtra($array, $booking_id){

        if(count($array) > 0){
            foreach ($array as $index => $item) {
                $bookingExtraModel = new BookingExtra();
                $bookingExtraModel->booking_id = (int)$booking_id;
                $bookingExtraModel->extra_id = (int)$index;
                $bookingExtraModel->save();
            }
        } else {
            return false;
        }
    }

    public static function getBookingExtraId($bookingExtraArr){
        if(count($bookingExtraArr) > 0) {
            $returnId = [];
            foreach ($bookingExtraArr as $key) {
                $returnId[] = $key['extra_id'];
            }
            return $returnId;
        } else {
            return false;
        }
    }
    
    public function actionCreate($startIndex = '', $endIndex = "", $roomId = '')
    {
        $session = Yii::$app->session;

        if(Yii::$app->user->identity->role == 28 || Yii::$app->user->identity->role == 27){
            return $this->redirect('/booking');
        }

        $booking = new Booking();
        $booking->status = BookingStatusEnum::NEW_BOOKING;
        if ($roomId) {
            if (!$startIndex) {
                $startIndex = TimestampHelper::currentDay();
            }
            if (!$endIndex) {
                $endIndex = TimestampHelper::currentDay();
            }
            $this->initBookingFromCalendar($startIndex, $endIndex, $roomId, $booking);
        } else {
            $startIndex = TimestampHelper::currentDay();
            $endIndex = TimestampHelper::currentDay();
            $roomId = NULL;
            $this->initBookingFromCalendar($startIndex, $endIndex, $roomId, $booking);
        }

        
        if($_GET['startIndex'] && $_GET['endIndex']){
            $init = $this->initPrices($booking);
        } elseif($startIndex && $endIndex && $roomId){
            $init = $this->initPrices($booking);
            if($session->has('startIndex')){
                $session->remove('startIndex');
            }
            if($session->has('endIndex')){
                $session->remove('endIndex');
            }
            $session->set('startIndex', $startIndex);
            $session->set('endIndex', $startIndex);
        }

        // Start Taxable For The Room
        if ($session->has('taxable_for_room')){
            $session->remove('taxable_for_room');
        }
        if($roomId){
            $roomModelTaxable = (new Query())->select(['*'])->from('vr_room')->where(['id' => $roomId])->one();
            if($roomModelTaxable['taxable'] == 0){
                $session->set('taxable_for_room', 0);
            }
        }
        // End Taxable For The Room

        $daySeasonId = $session->get('daySeasonId');
        // Это нада сделать
        if($init == 'not price'){
            Yii::$app->getSession()->setFlash('error', 'You can’t create a booking because unit doesn’t have prices for choosing period. Please, set up prices and season for this time period.');
            return Yii::$app->response->redirect(['calendar']);
        }
        if($init == 'week less'){
            Yii::$app->getSession()->setFlash('error', 'Booking less week');
            return Yii::$app->response->redirect(['calendar']);
        }
        if($init == 'month less'){
            Yii::$app->getSession()->setFlash('error', 'Booking less month');
            return Yii::$app->response->redirect(['calendar']);
        }
        if($init == 'day less'){
            Yii::$app->getSession()->setFlash('error', 'Booking less day');
            return Yii::$app->response->redirect(['calendar']);
        }

        if ($booking->load(\Yii::$app->request->post())) {
            ////var_dump($_POST);exit;
            if($booking->validate()){
                $roomId = $_POST['Booking']['rooms'][0]['room_id'];

                $arr[]['guest_id'] = $booking->guests;
                $guest=Guest::findOne($booking->guests);
                $booking->guests = $arr;
                $this->initPrices($booking);
                $this->removeSessionFromBookingMinimalDaysYes();

                $booking->room_extras = (int)$_POST['extrasTextRightHidden'];

                ////var_dump($booking);
                ////var_dump($_POST);exit;

                // Start Taxable For The Room
                if ($session->has('taxable_for_room')){
                    $session->remove('taxable_for_room');
                }
                // End Taxable For The Room

                // Start Occupancy Price
                $from = TimestampHelper::dateStringToDays($_POST['Booking']['rooms'][0]['from']);
                $to = TimestampHelper::dateStringToDays($_POST['Booking']['rooms'][0]['to']);
                $roomIdFromOccupancyPrice = $_POST['Booking']['rooms'][0]['room_id'];
                $guestCountFromOccupancyPrice = $_POST['Booking']['rooms'][0]['guests_count'];

                $linkModelRoom = LinkRooms::find()->where(['parent_room' => $roomId])->asArray()->all();
                if($linkModelRoom){
                    foreach($linkModelRoom as $room){
                        $linkModelRoomChildren = LinkRooms::find()->where(['parent_room' => (int)$room['children_room']])->asArray()->all();
                        if($linkModelRoomChildren){
                            foreach ($linkModelRoomChildren as $roomC){
                                if((int)$roomC['children_room'] == (int)$roomId){
                                    continue;
                                }
                                $this->createBlockFromLinkRoom($from, $to, $roomC['children_room'],$roomId);
                            }
                        }
                        $this->createBlockFromLinkRoom($from, $to, $room['children_room'],$roomId);
                    }
                }

                $returnOccupancyPrice = BookingController::getOccupancyPrice($from, $to, $roomIdFromOccupancyPrice, $guestCountFromOccupancyPrice);
                if($returnOccupancyPrice){
                    $booking->room_price += $returnOccupancyPrice['price'];
                }
                // End Occupancy Price

                // Start Pets Price
                $petsInt = (int)substr($_POST['Booking']['pets_price'], 1);
                $booking->pets_price = $petsInt;
                if(count($_POST['booking-pets-value']) > 0){
                    $petString = '';
                    $i = 0;
                    foreach($_POST['booking-pets-value'] as $petId => $petVal){
                        if($petVal == 0){
                            $i++;
                            continue;
                        }
                        if($i == 0){
                            $petString .= "$petId";
                        } else {
                            $petString .= ";$petId";
                        }
                        $i++;
                    }
                    $booking->booking_pets_type = $petString;
                    $booking->booking_pets_count = json_encode($_POST['booking-pets-count']);
                }
                // End Pets Price

                $notesText = $booking->notes;

                $startIndex2 = TimestampHelper::dayToDateStringMail($startIndex);
                $endIndex2  = TimestampHelper::dayToDateStringMail($endIndex);
                $room=Room::findOne($roomId);

                Yii::info('User was create booking in "'.$room->title.'" for "'.$guest->full_name.' '. $guest->last_name.'" from '.$startIndex2.' to '.$endIndex2,'security_log');

                if(Yii::$app->user->identity->role == 40 || Yii::$app->user->identity->role == 30 || Yii::$app->user->identity->role == 29){
                    $booking->status_or_source = BookingStatusOrSourceEnum::OWNER_BLOCK;
                } else {
                    $booking->status_or_source = BookingStatusOrSourceEnum::VROOMRES;
                }

                if (!\Yii::$app->request->isAjax) {
                    if ($booking->save()) {
                        // Start Save BookingExtra ID
                        if($_POST['checkBoxForBooking']){
                            $this->saveBookingExtra($_POST['checkBoxForBooking'], $booking->id);
                        }
                        // End Save BookingExtra ID

                        $BookingModel = (new Query())->select(['*'])->from('vr_booking')->where(['id' => $booking->id])->one();
                        $BookingRoomsModel = (new Query())->select(['*'])->from('vr_booking_rooms')->where(['booking_id' => $BookingModel['id']])->one();

                        if($BookingRoomsModel['period_id']){
                            $table = 'vr_calendar_period';
                            \Yii::$app->db->createCommand("UPDATE $table SET season_id=:season_id WHERE id=:id")
                                ->bindValue(':season_id', (int)$_POST['daySeasonId'])
                                //->bindValue(':season_id', $daySeasonId)
                                ->bindValue(':id', $BookingRoomsModel['period_id'])
                                ->execute();
                        }

                        //$this->sendEmailBooking($booking->id);
                        EmailSendService::sendEmailBooking($booking->id, 1);
                        if(Yii::$app->user->identity->role == 50){
                            $userId = UsersServices::getUsersProperty();
                        } elseif(Yii::$app->user->identity->role == 40){
                            $userId = UsersServices::getUsersHomeowner();
                        } elseif(Yii::$app->user->identity->role == 30){
                            $userId = UsersServices::getUsersBookkeeper();
                        } elseif(Yii::$app->user->identity->role == 29){
                            $userId = UsersServices::getUsersReservationist();
                        }
                        //$userId = Yii::$app->user->getId();
                        $time = time();
                        if(!empty($booking->notes)){
                            $notesModel = new BookingNotes();
                            $notesModel->booking_id = $booking->id;
                            $notesModel->text = $notesText;
                            $notesModel->user_id = $userId;
                            $notesModel->time = $time;
                            $notesModel->save();
                        }
                        if(Yii::$app->user->identity == 30){
                            return $this->redirect('/booking');
                        } else {
                            return $this->redirect('/calendar');
                        }

                    }
                }
                $session->remove('daySeasonId');
            } else {
                if($booking->rooms[0]['room_id']){
                    if($session->has('getRoomIdFromBooking')){
                        $session->remove('getRoomIdFromBooking');
                    }
                    $session->set('getRoomIdFromBooking',(int)$booking->rooms[0]['room_id']);
                }
                if($_POST['daySeasonId']){
                    if($session->has('daySeasonId')){
                        $session->remove('daySeasonId');
                    }
                    $session->set('daySeasonId',(int)$_POST['daySeasonId']);
                }
                $this->initPrices($booking);
                $booking->room_extras = (int)$_POST['extrasTextRightHidden'];
                if($session->has('checkBoxForBooking')){
                    $session->remove('checkBoxForBooking');
                }
                if(count($_POST['checkBoxForBooking']) > 0){
                    $session['checkBoxForBooking'] = $_POST['checkBoxForBooking'];
                }
                ////var_dump($_POST['checkBoxForBooking']);exit;
                /*//var_dump($_POST);
                //var_dump($booking->booking_pets_type);
                //var_dump($booking->rooms[0]['room_id']);
                //var_dump($booking);
                exit;*/
            }

        }
        //$session->remove('daySeasonId');

        if (\Yii::$app->request->isAjax) {
            return $this->renderPartial('_form', [
                'booking' => $booking,
            ]);
        } else {
            return $this->render('create', [
                'booking' => $booking,
            ]);
        }
    }

    public static function objectToArray($data)
    {
        if (is_array($data) || is_object($data))
        {
            $result = array();
            foreach ($data as $key => $value)
            {
                $result[$key] = self::objectToArray($value);
            }
            return $result;
        }
        return $data;
    }

    public static function getOccupancyPrice($from, $to, $roomIdFromOccupancyPrice, $guestCountFromOccupancyPrice){
        $occupancyLegend=[];
        $roomModel = (new Query())->select(['*'])->from('vr_room')->where(['id' => $roomIdFromOccupancyPrice])->one();
        $roomGuestMaxAmount = $roomModel['max_occupancy'];
        if($roomModel){
            if($roomModel['occupancy']){
                $returnPrice['price'] = 0;
                $price = $roomModel['occupancy_extra_price_value'];
                $occupancy = $roomModel['occupancy'];
                if($roomModel['occupancy_extra_price_type'] == 1){ // Per Night
                    for($start = $from; $start <= $to; $start++){
                        if($guestCountFromOccupancyPrice <= $occupancy){
                            continue;
                        }
                        if($guestCountFromOccupancyPrice > $roomGuestMaxAmount){
                            $returnPrice['error'] = "More than can accommodate a room. Max guest $roomGuestMaxAmount";
                            continue;
                        }
                    
                        $returnPrice['price'] += $price;
                    }
                        $returnPrice['type']='Per Night';
                        $returnPrice['guestprice']=$price;
                        $returnPrice['guestnum']=$guestCountFromOccupancyPrice;
                        
                        
                } elseif($roomModel['occupancy_extra_price_type'] == 2){ // Per Person
                    for($start = 1; $start <= $guestCountFromOccupancyPrice; $start++){
                        if($start <= $occupancy){
                            continue;
                        }
                        if($guestCountFromOccupancyPrice > $roomGuestMaxAmount){
                            $returnPrice['error'] = "More than can accommodate a room. Max guest $roomGuestMaxAmount";
                            continue;
                        }
                        $returnPrice['price'] += $price;
                    }
                        $returnPrice['type']='Per Night';
                        $returnPrice['guestprice']=$price;
                        $returnPrice['guestnum']=$guestCountFromOccupancyPrice;
             
                    //$cntOfGuest = $guestCountFromOccupancyPrice - $occupancy;
                    /*for($start = $from; $start <= $to; $start++){
                        if($guestCountFromOccupancyPrice > $roomGuestMaxAmount){
                            $returnPrice['error'] = "More than can accommodate a room. Max guest $roomGuestMaxAmount";
                            continue;
                        }
                        $returnPrice['price'] += $price;
                    }*/
                } elseif($roomModel['occupancy_extra_price_type'] == 3){ // Per Room
                    if($guestCountFromOccupancyPrice > $roomGuestMaxAmount){
                        $returnPrice['error'] = "More than can accommodate a room. Max guest $roomGuestMaxAmount";
                    }
                    $returnPrice['price'] = $price;
                    $returnPrice['type']='Per Room';
                    $returnPrice['guestprice']=$price;
                    $returnPrice['guestnum']=$guestCountFromOccupancyPrice;
                }
                return $returnPrice;

            } else {
                return 0;
            }
        } else {
            return "room not found";
        }

    }

    public static function getPetsPrice($from, $to, $roomIdFromOccupancyPrice, $petsBookingArr, $petsBookingCount){

        foreach($petsBookingCount as $key => $val){
            if($val == 0){
                unset($petsBookingCount[$key]);
            }
        }

        $roomModel = (new Query())->select(['*'])->from('vr_room')->where(['id' => $roomIdFromOccupancyPrice])->one();
        $roomPetsMaxAmount = $roomModel['pet_max_amount'];
        if($roomModel){
            if($roomModel['pet_friendly'] && !empty($roomModel['pet_price'])){
                $returnPrice['price'] = 0;
                $price = $roomModel['pet_price'];
                if($roomModel['pet_price_type'] == 1){ // Per Night
                    //Yii::warning($petsBookingCount, '$petsBookingCount');
                    if(count($petsBookingCount) > 0){
                        for($start = $from; $start <= $to; $start++){
                            if(array_sum($petsBookingCount) > $roomPetsMaxAmount){
                                $returnPrice['error'] = "The total pets should not be more than $roomPetsMaxAmount";
                                continue;
                            }
                            $returnPrice['price'] += $price;
                        }
                        $returnPrice['pet_price_type']='Per Night';
                        $returnPrice['pet_price']=$price;
                        $returnPrice['pet_count']=count($petsBookingCount);
                    }
                } elseif($roomModel['pet_price_type'] == 2){ // Per Room
                    if(count($petsBookingCount) > 0){
                        if(array_sum($petsBookingCount) > $roomPetsMaxAmount){
                            $returnPrice['error'] = "The total pets should not be more than $roomPetsMaxAmount";
                        }
                        $returnPrice['price'] = $price;
                        $returnPrice['pet_price_type']='Per Room';
                        $returnPrice['pet_price']=$price;
                        $returnPrice['pet_count']=count($petsBookingCount);
                    }
                } elseif($roomModel['pet_price_type'] == 3){ // Per Pet
                    if(count($petsBookingCount) > 0){
                        //Yii::warning(array_sum($petsBookingCount), 'pets for booking');
                        foreach ($petsBookingCount as $index) {
                            if(array_sum($petsBookingCount) > $roomPetsMaxAmount){
                                $returnPrice['error'] = "The total pets should not be more than $roomPetsMaxAmount";
                                continue;
                            }
                            $returnPrice['price'] += $price * $index;
                            $returnPrice['pet_price'] = $price;
                            $returnPrice['pet_price_type']='Per Pet';
                            $returnPrice['pet_count']=count($petsBookingCount);
                        }
                    }
                }
                return $returnPrice;

            } else {
                return 0;
            }
        } else {
            return "room not found";
        }

    }

    // public function sendEmailBooking($booking->id){}

    // For booking in user
    public function actionBookingUser(){
        Yii::info('BookingUser','security_log');
        $booking = new Booking();
        $booking->status = BookingStatusEnum::NEW_BOOKING;

        // Start Booking from and To
        $startIndex = TimestampHelper::currentDay();
        $endIndex = TimestampHelper::currentDay();
        $roomId = NULL;
        $this->initBookingFromCalendar($startIndex, $endIndex, $roomId, $booking);
        // End Booking from and To

        if ($booking->load(\Yii::$app->request->post()) && $booking->validate()) {
            $startIndex = $booking->roomsArray[0]->from;
            $endIndex = $booking->roomsArray[0]->to;

            $linkModelRoom = LinkRooms::find()->where(['parent_room' => $_POST['Booking']['rooms'][0]['room_id']])->asArray()->all();
            if($linkModelRoom){
                foreach($linkModelRoom as $room){
                    $this->createBlockFromLinkRoom($startIndex, $endIndex, $room['children_room']);
                }
            }

            $arr[]['guest_id'] = $booking->guests;
            $booking->guests = $arr;
            $this->initPrices($booking);
            $this->removeSessionFromBookingMinimalDaysYes();

            // Start Occupancy Price
            $from = TimestampHelper::dateStringToDays($_POST['Booking']['rooms'][0]['from']);
            $to = TimestampHelper::dateStringToDays($_POST['Booking']['rooms'][0]['to']);
            $roomIdFromOccupancyPrice = $_POST['Booking']['rooms'][0]['room_id'];
            $guestCountFromOccupancyPrice = $_POST['Booking']['rooms'][0]['guests_count'];
            $returnOccupancyPrice = BookingController::getOccupancyPrice($from, $to, $roomIdFromOccupancyPrice, $guestCountFromOccupancyPrice);
            if($returnOccupancyPrice){
                $booking->room_price += $returnOccupancyPrice['price'];
            }
            // End Occupancy Price

            $notesText = $booking->notes;

            /*$arr[]['guest_id'] = $booking->guests;
            $booking->guests = $arr;
            $this->initPrices($booking);

            $notesText = $booking->notes;*/
            if (!\Yii::$app->request->isAjax) {
                if ($booking->save()) {
                    //$userId = Yii::$app->user->getId();
                    if(Yii::$app->user->identity->role == 50){
                        $userId = UsersServices::getUsersProperty();
                    } elseif(Yii::$app->user->identity->role == 40){
                        $userId = UsersServices::getUsersHomeowner();
                    } elseif(Yii::$app->user->identity->role == 30){
                        $userId = UsersServices::getUsersBookkeeper();
                    } elseif(Yii::$app->user->identity->role == 29){
                        $userId = UsersServices::getUsersReservationist();
                    }

                    $time = time();
                    if(!empty($booking->notes)){
                        $notesModel = new BookingNotes();
                        $notesModel->booking_id = $booking->id;
                        $notesModel->text = $notesText;
                        $notesModel->user_id = $userId;
                        $notesModel->time = $time;
                        $notesModel->save();
                    }
                    return $this->redirect('/calendar');
                }
            }
        }

        return $this->render('create', [
            'booking' => $booking,
        ]);
    }

    // For booking in booking
    public function actionMake(){
        Yii::info('MakeBooking','security_log');
        if(Yii::$app->user->identity->role == 28 || Yii::$app->user->identity->role == 27 || Yii::$app->user->identity->role == 25){
            return $this->redirect('/booking');
        }

        $booking = new Booking();
        $booking->status = BookingStatusEnum::NEW_BOOKING;
        // Start Booking from and To
        $startIndex = TimestampHelper::currentDay();
        $endIndex = TimestampHelper::currentDay();
        $roomId = NULL;
        $this->initBookingFromCalendar($startIndex, $endIndex, $roomId, $booking);
        // End Booking from and To

        if ($booking->load(\Yii::$app->request->post()) && $booking->validate()) {
            $startIndex = $booking->roomsArray[0]->from;
            $endIndex = $booking->roomsArray[0]->to;

            $linkModelRoom = LinkRooms::find()->where(['parent_room' => $_POST['Booking']['rooms'][0]['room_id']])->asArray()->all();
            if($linkModelRoom){
                foreach($linkModelRoom as $room){
                    $this->createBlockFromLinkRoom($startIndex, $endIndex, $room['children_room']);
                }
            }

            $arr[]['guest_id'] = $booking->guests;
            $booking->guests = $arr;
            $this->initPrices($booking);
            $this->removeSessionFromBookingMinimalDaysYes();

            // Start Occupancy Price
            $from = TimestampHelper::dateStringToDays($_POST['Booking']['rooms'][0]['from']);
            $to = TimestampHelper::dateStringToDays($_POST['Booking']['rooms'][0]['to']);
            $roomIdFromOccupancyPrice = $_POST['Booking']['rooms'][0]['room_id'];
            $guestCountFromOccupancyPrice = $_POST['Booking']['rooms'][0]['guests_count'];
            $returnOccupancyPrice = BookingController::getOccupancyPrice($from, $to, $roomIdFromOccupancyPrice, $guestCountFromOccupancyPrice);
            if($returnOccupancyPrice){
                $booking->room_price += $returnOccupancyPrice['price'];
            }
            // End Occupancy Price

            $notesText = $booking->notes;

            /*$arr[]['guest_id'] = $booking->guests;
            $booking->guests = $arr;
            $this->initPrices($booking);

            $notesText = $booking->notes;*/
            if (!\Yii::$app->request->isAjax) {
                if ($booking->save()) {
                    //$userId = Yii::$app->user->getId();
                    if(Yii::$app->user->identity->role == 50){
                        $userId = UsersServices::getUsersProperty();
                    } elseif(Yii::$app->user->identity->role == 40){
                        $userId = UsersServices::getUsersHomeowner();
                    } elseif(Yii::$app->user->identity->role == 30){
                        $userId = UsersServices::getUsersBookkeeper();
                    } elseif(Yii::$app->user->identity->role == 29){
                        $userId = UsersServices::getUsersReservationist();
                    }

                    $time = time();
                    if(!empty($booking->notes)){
                        $notesModel = new BookingNotes();
                        $notesModel->booking_id = $booking->id;
                        $notesModel->text = $notesText;
                        $notesModel->user_id = $userId;
                        $notesModel->time = $time;
                        $notesModel->save();
                    }
                    return $this->redirect('/calendar');
                }
            }
        }

        return $this->render('_form_make', [
            'booking' => $booking,
        ]);
    }

    public function actionGuest($q = null, $id = null) {
       // Yii::info('BookingGuest','security_log');
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $out = ['results' => ['id' => '', 'text' => '']];
        //$userId = Yii::$app->user->identity->getId();
        if(Yii::$app->user->identity->role == 50){
            $userId = UsersServices::getUsersProperty();
        } elseif(Yii::$app->user->identity->role == 40){
            $userId = UsersServices::getUsersHomeowner();
        } elseif(Yii::$app->user->identity->role == 30){
            $userId = UsersServices::getUsersBookkeeper();
        } elseif(Yii::$app->user->identity->role == 29){
            $userId = UsersServices::getUsersReservationist();
        }

        if($q == ""){
            return $q;
        }
        if ($q != NULL) {
            //$query = new Query;
            $text = "SELECT `id`, CONCAT(full_name,' ', last_name) AS `text` FROM `vr_guest` WHERE (((`full_name` LIKE '%$q%') OR (`last_name` LIKE '%$q%')) AND (`user_id`=$userId)) OR (`another_users_id` LIKE '%$userId%')";
            /*$query->select("id, CONCAT(full_name, last_name) AS text")
                ->from('vr_guest')
                ->where(['like', 'full_name', $q])
                ->orWhere(['like', 'last_name', $q])
                ->andWhere(['user_id' => $userId])
                ->orWhere(['LIKE','another_users_id',$userId]);*/
                /*->limit(20);*/
            /*$command = $query->createCommand($text);
            $data = $command->queryAll();*/
            $data = Yii::$app->db->createCommand($text)->queryAll();
            $out['results'] = array_values($data);
        } elseif ($id > 0) {
            $guest = Guest::find($id)->select(['full_name'])->asArray()->one();
            if($guest){
                /*$name = "";
                if(!empty($guest['name_prefix'])){
                    $name .= $guest['name_prefix'] . " ";
                }
                if(!empty($guest['full_name'])){
                    $name .= $guest['full_name'] . " ";
                }
                if(!empty($guest['last_name'])){
                    $name .= $guest['last_name'] . " ";
                }
                if(!empty($guest['name_suffix'])){
                    $name .= $guest['name_suffix'] . " ";
                }*/

                $out['results'] = ['id' => $id, 'text' => $guest['full_name']];
                //$out['results'] = ['id' => $id, 'text' => $name];
            }
        }
        return $out;
    }

    public function actionGuestSave() {
        $idGuest = (int)$_POST['idGuest'];
        if($idGuest){
            $guestModel = (new Query())->select(['*'])->from('vr_guest')->where(['id' => $idGuest])->one();
            if($guestModel){
                $name = '';
                if(!empty($guest['full_name'])){
                    $name .= $guest['full_name'] . " ";
                }
                if(!empty($guest['last_name'])){
                    $name .= $guest['last_name'] . " ";
                }
                setcookie("guestForBooking", $guestModel['id'], time()+90);
                /*$returnGuest[$guestModel['id']] = $name;
                $session->set('guestForBooking',$returnGuest);*/
            } else {
                return false;
            }
        } else {
            return false;
        }
        //Yii::warning($_POST,'SaveGuest');
    }

    /**
     * @param $startIndex
     * @param $endIndex
     * @param $roomId
     * @param $booking
     */
    private function initBookingFromCalendar($startIndex, $endIndex, $roomId = NULL, $booking)
    {
        $bookingRoom = new BookingRoom();
        $bookingRoom->room_id = $roomId;
        $period = new CalendarPeriod();
        $period->from = $startIndex;
        $period->to = $endIndex;
        $period->item_id = $roomId;
        $period->item_type = PeriodTypeEnum::BOOKING_ROOM;
        $bookingRoom->periodTmp = $period;
        $booking->rooms = [$bookingRoom];
    }
    
    /**
     * @param $booking
     */
    private function initPrices($booking)
    {
        /** @var BookingService $bookingService */
        $bookingService = \Yii::$app->get('booking');
        $roomsTotalPrice = $bookingService->getRoomsTotalPrice($booking);
        if($roomsTotalPrice == 'not price'){
            return 'not price';
        }
        if($roomsTotalPrice == 'week less'){
            return 'week less';
        }
        if($roomsTotalPrice == 'month less'){
            return 'month less';
        }
        if($roomsTotalPrice == 'day less'){
            return 'day less';
        }
        //$room = Room::find()->where(['id' => 31])->with('extras')->asArray()->one();
        ////var_dump($room['extras']);
        ////var_dump($booking);

        $session = Yii::$app->session;
        $arr['daySeasonId'] = $session->get('daySeasonId');
        //$session->remove('daySeasonId');

        ////var_dump($arr['daySeasonId']);
        $roomsExtraTotalPrice = $bookingService->getExtrasTotalPrice($booking, $arr['daySeasonId']);
        $booking->room_price = $roomsTotalPrice;
        $booking->room_extras = $roomsExtraTotalPrice;
    }

    /**
     * @param $booking
     */
    private function initPricesAjax($booking)
    {
        /** @var BookingService $bookingService */
        $bookingService = \Yii::$app->get('booking');
        $roomsTotalPrice = $bookingService->getRoomsTotalPrice($booking);
        //$room = Room::find()->where(['id' => 31])->with('extras')->asArray()->one();
        ////var_dump($room['extras']);
        ////var_dump($booking);

        $session = Yii::$app->session;
        $arr['season'] = $session->get('season');
        $arr['daySeasonId'] = $session->get('daySeasonId');
        if($session->has('daySeasonIdFromAjaxRenderExtra')){
            $session->remove('daySeasonIdFromAjaxRenderExtra');
        }
        $session->set('daySeasonIdFromAjaxRenderExtra', $session->get('daySeasonId'));
        //Yii::warning($arr['daySeasonId'], 'aaaaaaaaa');
        $session->remove('season');
        $session->remove('daySeasonId');

        $conflict_period_booking = 0;
        if ($session->has('ConflictPeriodBooking')){
            $conflict_period_booking = $session->get('ConflictPeriodBooking');
            //Yii::warning($res,'ConflictPeriodBookingItem');
            $session->remove('ConflictPeriodBooking');
        }

        if(is_array($conflict_period_booking)){
            $calendar_period_id = (int)$conflict_period_booking['id'];
            $idBlockAndBooking = $conflict_period_booking['item_root_id'];

            $booking_from = TimestampHelper::dayToDateStringMail($conflict_period_booking['from']);
            $booking_to = TimestampHelper::dayToDateStringMail($conflict_period_booking['to']);
            
            if($conflict_period_booking['item_type'] == 2){
                /*$booking_rooms_table = (new Query())->select(['*'])->from('vr_booking_rooms')->where(['period_id' => $calendar_period_id])->one();
                if($booking_rooms_table){
                    $booking_id = $booking_rooms_table['booking_id'];
                }*/
                $arr['error_conflict_period_booking'] = "You have a date conflict Booking Id #$idBlockAndBooking  From {$booking_from} To {$booking_to}";
            } else {
                //$block_id = $conflict_period_booking['item_root_id'];
                $arr['error_conflict_period_booking'] = "You have a date conflict Block Id #$idBlockAndBooking  From {$booking_from} To {$booking_to}";
            }
        }

        //\yii\helpers\VarDumper::dump($booking,11,1);
        
        $nights=$booking->roomsArray[0]->periodTmp->to - $booking->roomsArray[0]->periodTmp->from;
        
        //Yii::warning($booking->periodTmp->to,'b');;
        
       // $roomsExtraTotalPrice = $bookingService->getExtrasTotalPrice($booking, $arr['daySeasonId']);
        $roomsExtraLegendPrice = $bookingService->getExtrasTotalLegendPrice($booking, $arr['daySeasonId']);
        //Yii::warning($roomsExtraTotalPrice,'getExtrasTotalLegendPrice');
        
       // Yii::warning($roomsExtraTotalPrice['roomTotalExtraPriceAll'],'all total');
      
        $booking->room_price = $roomsTotalPrice;
        $booking->room_extras = $roomsExtraTotalPrice;
        $roomTotalExtraPriceAll=0;
        
       
       
        foreach($roomsExtraLegendPrice as $price)
        {   
            $roomTotalExtraPriceAll=$price['roomTotalExtraPriceAll'];
        }    
        
        $arr['room_price'] = $roomsTotalPrice;
       // $arr['room_extras'] = $roomsExtraTotalPrice;
        $arr['room_extras'] = $roomTotalExtraPriceAll;
         $roomsExtraLegendPrice= array_pop( $roomsExtraLegendPrice); //берем только экстру 1 комнаты нужно переделать на все
         unset($roomsExtraLegendPrice['roomTotalExtraPriceAll']);
         $arr['room_extras_legend'] =$roomsExtraLegendPrice;
         $arr['nights']=$nights+1;
         
        Yii::warning($arr['room_extras_legend'],'all total');
      
        return $arr;
    }

    public function actionAjaxRenderExtra(){
        //daySeasonIdFromAjaxRenderExtra
        $session = Yii::$app->session;
        $roomId = (int)($_POST['roomId']);
        if($session->has('daySeasonIdFromAjaxRenderExtra')){
            $daySeasonId = (int)($session->get('daySeasonIdFromAjaxRenderExtra'));
            $session->remove('daySeasonIdFromAjaxRenderExtra');
        } else {
            $daySeasonId = NULL;
        }
        //$daySeasonId = (int)($_POST['daySeasonId']);
        $data = ExtraRoomsRecord::find()
            ->with('extra')
            ->where(['room_id' => $roomId])
            ->all();
        if($data){
            return $this->renderAjax('ajax-render-extra',['data' => $data, 'roomId' => $roomId, 'daySeasonId' => $daySeasonId]);
        } else {
            return false;
        }

    }

    public function actionAjaxUpdatePrice(){

        $session = Yii::$app->session;
        if($_POST['JsBookingMinimalDaysYes']){
            $session->set('BookingMinimalDaysYes', 1);
        }

        $startIndex = TimestampHelper::dateStringToDays($_POST['startIndex']);
        $endIndex = TimestampHelper::dateStringToDays($_POST['endIndex']);
        $roomId = $_POST['roomId'];
        $countOfGuest = $_POST['countOfGuest'];
        $updateBookingId = (int)($_POST['updateBookingId']);
        //$petsBooking = (int)($_POST['petsBooking']);
        $petsBookingArr = json_decode($_POST['petsBookingArr']);
        $petsBookingCount = json_decode($_POST['petsBookingCount']);

        $extraNotCheck = $_POST['extraNotCheck'];

        if($updateBookingId > 0){
            $session->set('updateBookingId', $updateBookingId);
        }

        if($extraNotCheck != 'false'){
            $session->set('extraNotCheck', $extraNotCheck);
        }

        $booking = new Booking();
        $booking->status = BookingStatusEnum::NEW_BOOKING;
        if ($roomId) {
            if (!$startIndex) {
                $startIndex = TimestampHelper::currentDay();
            }
            if (!$endIndex) {
                $endIndex = TimestampHelper::currentDay();
            }
            $this->initBookingFromCalendar($startIndex, $endIndex, $roomId, $booking);
        }

        $arr = $this->initPricesAjax($booking);

        if($session->has('updateBookingId')){
            $session->remove('updateBookingId');
        }

        if($countOfGuest){
            // Start Occupancy Price
            if(!is_string($arr['room_price'])){
                $returnOccupancyPrice = BookingController::getOccupancyPrice($startIndex, $endIndex, $roomId, $countOfGuest);
                if($returnOccupancyPrice){
                    $arr['room_price'] += $returnOccupancyPrice['price'];
                    $arr['occupancy_legend']=$returnOccupancyPrice;
                    if($returnOccupancyPrice['error']){
                        $arr['error_guest_booking'] = $returnOccupancyPrice['error'];
                    }
                }
            }
            // End Occupancy Price
        }

        // Start Pets Price
        //if($petsBooking){
            if(!is_string($arr['room_price'])){
            //Yii::warning($petsBookingCount, 'test');
                $returnPetsPrice = BookingController::getPetsPrice($startIndex, $endIndex, $roomId, $petsBookingArr, $petsBookingCount);
                if($returnPetsPrice){
                    //$arr['room_price'] += $returnPetsPrice;
                    $arr['return_pets_price'] = $returnPetsPrice['price'];
                    $arr['pets_legend'] = $returnPetsPrice;
                    if($returnPetsPrice['error']){
                        $arr['error'] = $returnPetsPrice['error'];
                    }
                }
            }
        //}
        //Yii::warning($arr, 'error Pets');
        // End Pets Price

        if($session->has('extraNotCheck')){
            $session->remove('extraNotCheck');
        }
        $taxable_sum = $session->get('not_taxable');
        if($session->has('not_taxable')){
            $session->remove('not_taxable');
        }
        $arr['not_taxable'] = $taxable_sum;
        // For Search Tax
        $groupSeasonId = $arr['season'];
        $tax = 0;
        $taxModelForCalendar = (new Query())->select(['*'])->from('vr_tax')->where(['<=', 'from', $startIndex])->andWhere(['>=', 'to', $endIndex])->andWhere(['season_group_id' => $groupSeasonId])->one();
        if($taxModelForCalendar){
            $arr['tax'] = $taxModelForCalendar['tax'];
        }
        /*$taxModelForCalendar = Tax::find()
            ->where(['room_id' => $roomId, 'season_group_id' => $groupSeasonId])
            ->andWhere(['<=' ,'from', $startIndex])
            ->andWhere(['>=' ,'to', $endIndex])
            ->asArray()
            ->one();
        if($taxModelForCalendar){
            $arr['tax'] = $taxModelForCalendar['tax'];
        }*/

        // Start Taxable For The Room
        if ($session->has('taxable_for_room')){
            $session->remove('taxable_for_room');
        }
        if($roomId){
            $roomModelTaxable = (new Query())->select(['*'])->from('vr_room')->where(['id' => $roomId])->one();
            if($roomModelTaxable['taxable'] == 0){
                $arr['taxable_for_room'] = 0; //1
            } else {
                $arr['taxable_for_room'] = 1; //0
            }
        }
        // End Taxable For The Room

        $this->removeSessionFromBookingMinimalDaysYes();
        if ($session->has('BookingMinimalDaysNumber')){
            $arr['min_stay'] = $session->get('BookingMinimalDaysNumber');
            $session->remove('BookingMinimalDaysNumber');
        }
        
        $arr['extra_Constants']=PriceViewTypeExtraEnum::getValueToNameArray(); 

        return json_encode($arr);
    }

    public function actionAjaxRenderPets(){
        $roomId = $_POST['roomId'];
        return $this->renderPartial('ajax-render-pets', ['roomId' => $roomId]);
    }
    
    public function actionAjaxSendMail()
    {
        $mailBody= $_POST['mail_body'];
        $mailSubject= $_POST['mail_subj'];
        $guest_id= $_POST['guest_id'];
        
        $guest=Guest::find()->where(['id'=>$guest_id])->one();
        $userId = Yii::$app->user->identity->getId();
        
        if($guest->email)
        { $res= \app\services\EmailSendService::sendEmail($userId,$guest->email,$mailSubject,$mailBody);
          return '<p class="text-blue">Message send</p>';
        }
        else
        {
           return '<p class="text-red">Can`t find guest or no email filled</p>';
        }
    }
      
    public function removeSessionFromBookingMinimalDaysYes(){
        $session = Yii::$app->session;
        if($session->has('BookingMinimalDaysYes')){
            $session->remove('BookingMinimalDaysYes');
        }
    }

    public function actionUpdateSessionFromBookingMinimalDaysYes(){
        $session = Yii::$app->session;
        $session->set('BookingMinimalDaysYes', 1);
    }
    
    public function actionUpdate($id)
    {   Yii::info('BookingUpdate','security_log');
        ////var_dump(Yii::$app->user->can('moder'));
        $session = Yii::$app->session;

        $booking = Booking::findOne($id);
        $old=$booking->attributes();
        // Start Taxable For The Room
        if ($session->has('taxable_for_room')){
            $session->remove('taxable_for_room');
        }
        if($booking->rooms[0]['room_id']){
            $roomModelTaxable = (new Query())->select(['*'])->from('vr_room')->where(['id' => $booking->rooms[0]['room_id']])->one();
            if($roomModelTaxable['taxable'] == 0){
                $session->set('taxable_for_room', 0);
            }
        }
        // End Taxable For The Room

        //$notesAll = BookingNotes::find()->where(['booking_id' => $booking->id])->with('user')->asArray()->all();
        $notesAll = BookingNotes::find()->where(['booking_id' => $booking->id])->asArray()->all();

        //For Widget Select2
        $idGuest = $booking->guests[0]['guest_id'];
        $GuestSearch = Guest::find()->where(['id' => $idGuest])->one();
        if(!$GuestSearch['full_name']){
            throw new HttpException(404);
        }
        $booking->guests = $GuestSearch['full_name'];
        $booking->guestsId = $idGuest;

        // Start For delete calendar period
        $calendarId = NULL;
        $Book = (new \yii\db\Query())
            ->select('*')
            ->from('vr_booking')
            ->where(['id' => $id])
            ->one();
        if($Book){
            $BookRooms = (new \yii\db\Query())
                ->select('*')
                ->from('vr_booking_rooms')
                ->where(['booking_id' => $Book['id']])
                ->one();
            if($BookRooms){
                $CalendarPeriod = (new \yii\db\Query())
                    ->select('*')
                    ->from('vr_calendar_period')
                    ->where(['id' => $BookRooms['period_id']])
                    ->one();
                if($CalendarPeriod){
                    $calendarId = $CalendarPeriod['id'];
                }
            }
        }
        // End For delete calendar period
        if ($booking->load(\Yii::$app->request->post()) && $booking->validate()) {
            ////var_dump($_POST);exit;
            $new=$booking->attributes();
    
            //TableAmount
            if($_POST['TableAmount']){
                //$id = Yii::$app->user->identity->getId();
                if(Yii::$app->user->identity->role == 50){
                    $id = UsersServices::getUsersProperty();
                } elseif(Yii::$app->user->identity->role == 40){
                    $id = UsersServices::getUsersHomeowner();
                } elseif (Yii::$app->user->identity->role == 30){
                    $id = UsersServices::getUsersBookkeeper();
                } elseif (Yii::$app->user->identity->role == 29){
                    $id = UsersServices::getUsersReservationist();
                }

                $rows = (new \yii\db\Query())
                    ->select('*')
                    ->from('vr_user')
                    ->where(['id' => $id])
                    ->one();
                $name = $rows['username'] . "  " . $rows['second_name'];
                foreach($_POST['TableAmount'] as $key => $val){
                        $booking_payment = new BookingPayment();
                        $booking_payment->booking_id = $booking->id;
                        $booking_payment->amount = (int)($val);
                        $booking_payment->type_of_payment = (int)($_POST['TableType'][$key]);
                        $booking_payment->time_of_payment = time();
                        $booking_payment->notes_entered = $_POST['TableNotes'][$key];
                        $booking_payment->who_entered = $name;
                        $booking_payment->save();
                }
            }

            //TableAmountCredit
            if($_POST['TableAmountCredit']){
                //$id = Yii::$app->user->identity->getId();
                if(Yii::$app->user->identity->role == 50){
                    $id = UsersServices::getUsersProperty();
                } elseif(Yii::$app->user->identity->role == 40){
                    $id = UsersServices::getUsersHomeowner();
                } elseif(Yii::$app->user->identity->role == 30){
                    $id = UsersServices::getUsersBookkeeper();
                } elseif(Yii::$app->user->identity->role == 29){
                    $id = UsersServices::getUsersReservationist();
                }

                $rows = (new \yii\db\Query())
                    ->select('*')
                    ->from('vr_user')
                    ->where(['id' => $id])
                    ->one();
                $name = $rows['username'] . "  " . $rows['second_name'];
                foreach($_POST['TableAmountCredit'] as $key => $val){
                    $booking_payment = new BookingPayment();
                    $booking_payment->booking_id = $booking->id;
                    $booking_payment->amount = (int)($val);
                    $booking_payment->type_of_payment = (int)($_POST['TableTypeCredit'][$key]);
                    $booking_payment->time_of_payment = time();
                    $booking_payment->notes_entered = $_POST['TableNotesCredit'][$key];
                    $booking_payment->who_entered = $name;
                    $booking_payment->credit = 1;
                    $booking_payment->save();
                }
            }

            if($_POST['Booking']['status'] == 5){
                EmailSendService::sendEmailBooking($booking->id, 7);
            }
            $arr[]['guest_id'] = $booking->guests;
            $booking->guests = $arr;
            /** @var BookingService $bookingService */

            $session = Yii::$app->session;
            if($calendarId != false){
                CalendarPeriod::deleteAll("id = $calendarId");
            }
            $this->initPrices($booking);
            $this->removeSessionFromBookingMinimalDaysYes();

            $booking->room_extras = (int)$_POST['extrasTextRightHidden'];

            // Start 22.05.2017
            // Start Occupancy Price
            $from = TimestampHelper::dateStringToDays($_POST['Booking']['rooms'][0]['from']);
            $to = TimestampHelper::dateStringToDays($_POST['Booking']['rooms'][0]['to']);
            $roomIdFromOccupancyPrice = $_POST['Booking']['rooms'][0]['room_id'];
            $guestCountFromOccupancyPrice = $_POST['Booking']['rooms'][0]['guests_count'];
            $returnOccupancyPrice = BookingController::getOccupancyPrice($from, $to, $roomIdFromOccupancyPrice, $guestCountFromOccupancyPrice);
            if($returnOccupancyPrice){
                $booking->room_price += $returnOccupancyPrice['price'];
            }
            // End Occupancy Price

            // Start Pets Price
            $petsInt = (int)substr($_POST['Booking']['pets_price'], 1);
            $booking->pets_price = $petsInt;
            //if((int)$_POST['booking-pets-check'] == 'on'){
            if(count($_POST['booking-pets-value']) > 0){
                $petString = '';
                $i = 0;
                foreach($_POST['booking-pets-value'] as $petId => $petVal){
                    if($petVal == 0){
                        $i++;
                        continue;
                    }
                    if($i == 0){
                        $petString .= "$petId";
                    } else {
                        $petString .= ";$petId";
                    }
                    $i++;
                }
                $booking->booking_pets_type = $petString;
                $booking->booking_pets_count = json_encode($_POST['booking-pets-count']);
            }
            // End Pets Price
            // End 22.05.2017

            $booking->room_extras = (int)$_POST['extrasTextRightHidden'];

            $daySeasonId = $session->get('daySeasonId');
            $session->remove('daySeasonId');

            if (!\Yii::$app->request->isAjax) {
                if ($booking->save()) {
                    // Start Save BookingExtra ID
                    BookingExtra::deleteAll(['booking_id' => $booking->id]);
                    if($_POST['checkBoxForBooking']){
                        $this->saveBookingExtra($_POST['checkBoxForBooking'], $booking->id);
                    }
                    // End Save BookingExtra ID

             
            $startIndex2 = TimestampHelper::dayToDateStringMail($CalendarPeriod['from']);
            $endIndex2  = TimestampHelper::dayToDateStringMail($CalendarPeriod['to']);
            $room=Room::findOne($CalendarPeriod['item_id']);
            
            $toLogChanges= array_diff_assoc($new,$old);
            $toLogChanges=json_encode($toLogChanges);

            Yii::info('User was edit booking in "'.$room->title.'" for "'.$GuestSearch['full_name'].'" from '.$startIndex2.' to '.$endIndex2.' Changes'.$toLogChanges,'security_log');
      
                    
                    $BookingModel = (new Query())->select(['*'])->from('vr_booking')->where(['id' => $booking->id])->one();
                    $BookingRoomsModel = (new Query())->select(['*'])->from('vr_booking_rooms')->where(['booking_id' => $BookingModel['id']])->one();

                    if($BookingRoomsModel['period_id']){
                        $table = 'vr_calendar_period';
                        \Yii::$app->db->createCommand("UPDATE $table SET season_id=:season_id WHERE id=:id")
                            ->bindValue(':season_id', $daySeasonId)
                            ->bindValue(':id', $BookingRoomsModel['period_id'])
                            ->execute();
                    }
                    if(Yii::$app->user->identity == 30){
                        return $this->redirect('/booking');
                    } else {
                        return $this->redirect('/calendar');
                    }

                }
            }
        }
      
        if (\Yii::$app->request->isAjax) {
            return $this->renderPartial('_form', [
                'booking' => $booking,
                'notesAll' => $notesAll,
            ]);
        } else {
            return $this->render('update', [
                'booking' => $booking,
                'notesAll' => $notesAll,
            ]);
        }
    }

    public function actionAjaxUncheckedExtra(){

        $idExtra = (int)($_POST['idExtra']);
        $idRoom = (int)($_POST['idRoom']);
        $extraId = (int)($_POST['extraId']);

        if($idExtra && $idRoom && $extraId){
            ExtraRoomsRecord::deleteAll(['id' => $idExtra, 'room_id' => $idRoom, 'extra_id' => $extraId]);
        } else {
            exit;
        }
    }

    public function actionAjaxCheckedExtra(){

        $idExtra = (int)($_POST['idExtra']);
        $idRoom = (int)($_POST['idRoom']);
        $extraId = (int)($_POST['extraId']);

        if($idExtra && $idRoom && $extraId){
            $model = new ExtraRoomsRecord();
            $model->id = $idExtra;
            $model->room_id = $idRoom;
            $model->extra_id = $extraId;
            $model->save();
        } else {
            exit;
        }
    }

    public function actionAddAjaxGuest(){

        $model = new Guest();

        return $this->renderAjax('add-ajax-guest', ['model' => $model]);
    }

    public function actionAddAjaxGuestTest(){
  //Yii::warning($_POST);
        if($_POST['Guest']['email'] && $_POST['Guest']['full_name']){
            $model = new Guest();
            $model->email = $_POST['Guest']['email'];
            $model->full_name = $_POST['Guest']['full_name'];
            $model->last_name = $_POST['Guest']['last_name'];//'last name';
            
            if(Yii::$app->user->identity->role == 50){
                $model->user_id = UsersServices::getUsersProperty();
            } elseif(Yii::$app->user->identity->role == 40){
                $model->user_id = UsersServices::getUsersHomeowner();
            } elseif(Yii::$app->user->identity->role == 30){
                $model->user_id = UsersServices::getUsersBookkeeper();
            } elseif(Yii::$app->user->identity->role == 29){
                $model->user_id = UsersServices::getUsersReservationist();
            }

            //$model->user_id = Yii::$app->user->identity->getId();
            $model->validate();
            Yii::warning($model->errors);
            
            if($model->save()){
                $res = Guest::find()->where(['id' => $model->id])->asArray()->one();
                return json_encode($res);
            } else {
                return 0;
            }
        }
    }
    
    /**
     * @param $roomsPrice
     * @param $extrasPrice
     * @param $adjustment
     *
     * @return int
     */
    public function getSubtotalPrice($roomsPrice, $extrasPrice, $adjustment)
    {
        $subtotalPrice = $roomsPrice + $extrasPrice;
        if ($adjustment) {
            $subtotalPrice += intval($adjustment);
            return $subtotalPrice;
        }
        return $subtotalPrice;
    }
    
    /**
     * @param $subtotalPrice
     * @param $tax
     *
     * @return float|int
     */
    public function getTotalPrice($subtotalPrice, $tax)
    {
        $totalPrice = $subtotalPrice;
        if ($tax) {
            $totalPrice += $totalPrice * $tax / 100;
            return $totalPrice;
        }
        return $totalPrice;

    }

    // Add Notes
    public function actionAddNotes(){

        $text = $_POST['text'];
        $userId = $_POST['userId'];
        $bookingId = $_POST['bookingId'];
        $time = time();

        if($text && $userId && $bookingId){

            $notesModel = new BookingNotes();
            $notesModel->booking_id = $bookingId;
            $notesModel->text = $text;
            $notesModel->user_id = $userId;
            $notesModel->time = $time;
            if($notesModel->save()){

                //$notesAll = BookingNotes::find()->where(['booking_id' => $bookingId])->with('user')->asArray()->all();
                $notesAll = BookingNotes::find()->where(['booking_id' => $bookingId])->asArray()->all();
                return $this->renderAjax('ajax-render-notes', ['notesAll' => $notesAll]);

            } else {
                return "notes not saved";
            }
        } else {
            return "empty notes || other";
        }
    }

    public function createBlockFromLinkRoom($startIndex = '', $endIndex = "", $roomId = '', $mainRoomId = NULL)
    {
        if($mainRoomId){
            $roomMainModel = (new Query())->select(['*'])->from('vr_room')->where(['id' => (int)$mainRoomId])->one();
            if($roomMainModel){
                $description = "Block was create by booking ";
                $description .= $roomMainModel['title'];
            } else {
                $description = NULL;
            }
        } else {
            $description = NULL;
        }
        $model = new BookingBlock();
        //$model->user_id = \Yii::$app->user->id;
        if(Yii::$app->user->identity->role == 50){
            $model->user_id = UsersServices::getUsersProperty();
        } elseif(Yii::$app->user->identity->role == 40){
            $model->user_id = UsersServices::getUsersHomeowner();
        } elseif(Yii::$app->user->identity->role == 30){
            $model->user_id = UsersServices::getUsersBookkeeper();
        } elseif(Yii::$app->user->identity->role == 29){
            $model->user_id = UsersServices::getUsersReservationist();
        }

        if ($startIndex && $endIndex && $roomId) {
            $calendarPeriodNotSave = CalendarPeriod::find()->where(['item_id' => $roomId])->andWhere(['>=' ,'from', $startIndex])->andWhere(['<=' ,'to', $startIndex])->asArray()->one();
            if(!$calendarPeriodNotSave){
                $period = new CalendarPeriod();
                $period->from = $startIndex;
                $period->to = $endIndex;
                $period->item_id = $roomId;
                $period->item_type = PeriodTypeEnum::BOOKING_BLOCK;
                $model->periodsArray = [
                    $period,
                ];
                $model->description = $description;
                $model->room_id = $roomId;
                if ($model->save()) {
                    return true;
                }
            } else {
                // Можна вывести ошибку
            }

        }
    }

    public function actionReSendEmail(){
        Yii::info('BookingReSendEmail','security_log');
        $bookingId = (int)($_POST['booking']);
        if(is_int($bookingId)){
            EmailSendService::sendEmailBooking($bookingId, 1);
        } else {
            return false;
        }
    }

    public function actionReSendEmailTable(){
        Yii::info('BookingReSendEmail','security_log');
        $bookingId = (int)($_POST['booking']);
        if(is_int($bookingId)){
            EmailSendService::sendEmailBooking($bookingId, 4);
        } else {
            return false;
        }
    }

    public function actionAddTableTr(){
        $booking_id = $_POST['booking_id'];
        $count_tr = $_POST['count_tr'] + 1;
        //$id = Yii::$app->user->identity->getId();
        if(Yii::$app->user->identity->role == 50){
            $id = UsersServices::getUsersProperty();
        } elseif(Yii::$app->user->identity->role == 40){
            $id = UsersServices::getUsersHomeowner();
        } elseif(Yii::$app->user->identity->role == 30){
            $id = UsersServices::getUsersBookkeeper();
        } elseif(Yii::$app->user->identity->role == 29){
            $id = UsersServices::getUsersReservationist();
        }

        $rows = (new \yii\db\Query())
            ->select('*')
            ->from('vr_user')
            ->where(['id' => $id])
            ->one();
        $name = $rows['username'] . "  " . $rows['second_name'];
        return $this->renderAjax('ajax-render-table', ['count_tr' => $count_tr, 'name' => $name]);
    }

    public function actionAddCreditTableTr(){
        $booking_id = $_POST['booking_id'];
        $count_tr = $_POST['count_tr'] + 1;
        //$id = Yii::$app->user->identity->getId();
        if(Yii::$app->user->identity->role == 50){
            $id = UsersServices::getUsersProperty();
        } elseif(Yii::$app->user->identity->role == 40){
            $id = UsersServices::getUsersHomeowner();
        } elseif(Yii::$app->user->identity->role == 30){
            $id = UsersServices::getUsersBookkeeper();
        } elseif(Yii::$app->user->identity->role == 29){
            $id = UsersServices::getUsersReservationist();
        }

        $rows = (new \yii\db\Query())
            ->select('*')
            ->from('vr_user')
            ->where(['id' => $id])
            ->one();
        $name = $rows['username'] . "  " . $rows['second_name'];
        return $this->renderAjax('ajax-render-table-credit', ['count_tr' => $count_tr, 'name' => $name]);
    }
    
    public function actionDelete($id)
    {   Yii::info('BookingDelete','security_log');
        return $this->redirect('/booking'); // Если надо удалить розкоментируй
        /*$booking_room = BookingRoom::find()->where(['booking_id' => $id])->asArray()->one();
        CalendarPeriod::deleteAll(['id' => $booking_room['period_id']]);
        //RoomPrice::deleteAll(['room_id' => $id]);
        Booking::deleteAll(['id' => $id]);
        return $this->redirect('/booking');*/
    }

    public function actionReturn()
    {
        ////var_dump($_POST);
        ////var_dump($_GET);
        $table = 'vr_booking';
        $status = BookingStatusEnum::FULLY_PAID;
        $session = Yii::$app->session;
        $session->open();
        $booking_id = $session->get('booking_id_for_email');
        unset($session['booking_id_for_email']);
        if($_POST['payment_status'] == 'Completed' && $booking_id){
            \Yii::$app->db->createCommand("UPDATE $table SET status = $status WHERE id=:id")
                ->bindValue(':id', $booking_id)
                ->execute();

            EmailSendService::sendEmailBooking($booking_id, 2);
        }
    }

    public function actionNotify()
    {
        ////var_dump($_POST);
        ////var_dump($_GET);
        $session = Yii::$app->session;
        $session->open();
        $booking_id = $session->get('booking_id_for_email');
        EmailSendService::sendEmailBooking($booking_id, 3);
    }

    public function actionStripe(){
        Yii::info('BookingStripe','security_log'); 
        if(Yii::$app->request->post()){

            $stripe = array(
                "secret_key"      => "sk_test_gdSqCztUtbOziRWt3ozm4vMg",
                "publishable_key" => "pk_test_F0nAZGKxoHgjfMtXwMuPNju4"
            );

            Stripe::setApiKey($stripe['secret_key']);

            $token  = $_POST['stripeToken'];

            $customer = Customer::create(array(
                //'email' => 'customer@example.com',
                'source'  => $token
            ));

////var_dump(get_class_methods($customer));
////var_dump($customer->keys());

            $charge = Charge::create(array(
                'customer' => $customer->id,
                'email' => $customer->email,
                'amount'   => 2000,
                'currency' => 'usd'
            ));
            ////var_dump($charge);
            echo '<h1>Successfully charged $50.00!</h1>';
        } else {
            return $this->redirect('/booking');
        }
    }

    public function actionAuthorize(){
        Yii::info('BookingAuthorize','security_log'); 
        if(isset($_POST['SendAuthorize'])){

            $first_name = $this->formatstr($_POST['FirstName']);
            $last_name = $this->formatstr($_POST['LastName']);
            $cart_number = $_POST['CartNumber'];
            //var_dump($cart_number);
            $date = (int)$_POST['ExpirationDate'];

            define("AUTHORIZENET_API_LOGIN_ID", "7u5Z4jRW");
            define("AUTHORIZENET_TRANSACTION_KEY", "2GR8M33rZ2um6JxE");
            define("AUTHORIZENET_SANDBOX", true);
            $sale           = new AuthorizeNetAIM;
            $sale->amount   = "66.66";
            $sale->card_num = $cart_number;//'6011000000000012';
            $sale->exp_date = $date;//'11/16';
            $sale->ship_to_first_name = $first_name;
            $sale->ship_to_last_name = $last_name;
            $sale->phone = '555-55-555';
            $response = $sale->authorizeAndCapture();
            if ($response->approved) {
                //var_dump($response);
                $transaction_id = $response->transaction_id;
                //return $this->redirect(Yii::$app->request->referrer);
            }
        }
        return $this->renderAjax('authorize');
    }

    public function formatstr($str)
    {
        $str = trim($str);
        $str = stripslashes($str);
        $str = htmlspecialchars($str);
        return $str;
    }
}