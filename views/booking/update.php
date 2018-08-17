<?
use app\components\widget\box\Box;
use app\models\booking\Booking;
use app\models\guest\Guest;
use app\models\room\Room;
use app\widgets\MultiplyInput;
use kartik\form\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/** @var Booking $booking */
/** @var \yii\web\View $this */
$this->title = 'Update Booking #'.$booking->id;

$this->registerJsFile('@web/src-web/js/booking-form.js', ['position' => \yii\web\View::POS_END, 'depends' => [
    'app\assets\AppAsset',
],]);
?>
<div class="js-booking-container">
    <?= $this->render('_form', [
        'booking' => $booking,
        'notesAll' => $notesAll,
    ]) ?>
</div>
