<?php
use yii\web\View;

/**
 * @var View $this
 */
$this->title = 'Dashboard';
?>


<div class="dashboard-day-select js-dashboard-day-select">

    <button class="btn btn-default js-yesterday">
        Yesterday
    </button>
    <button class="btn btn-default js-today active-btn">
        Today
    </button>
    <button
        class="btn btn-default js-tomorrow ">
        Tomorrow
    </button>
    <button
        class="btn btn-default js-datepicker">
        Select Day
    </button>
</div>

<div class="dashboard-container js-dashboard-container">
 
    <?= $this->render('_dashboard', [
        'checkInDataProvider'        => $checkInDataProvider,
        'checkOutDataProvider'       => $checkOutDataProvider,
        'checkInDataProviderSameDay' => $checkInDataProviderSameDay,
        'availableRoomsDataProvider' => $availableRoomsDataProvider,
        'checkInDataProviderToDayReservation' => $checkInDataProviderToDayReservation,
        'roomCountLabel'             => $roomCountLabel,
        'availableRoomsRelative'     => $availableRoomsRelative,
        'dashboardDay'               => $dashboardDay,
    ]) ?>
</div>