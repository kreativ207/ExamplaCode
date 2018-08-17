<?
use app\components\widget\box\Box;
use app\models\address\CountryRecord;
use app\models\guest\Guest;
use kartik\form\ActiveForm;
use yii\widgets\MaskedInputAsset;

MaskedInputAsset::register($this);
/** @var Guest $model */
/** @var CountryRecord[] $countries */
?>
<?
$form = ActiveForm::begin([
            'id' => 'form-ajax-add-guest',
        ]);
?>
    <div class="row">
        <div class="col-md-12">
            <?= $form->field($model, 'email')->textInput([
                'class' => 'ff-line-text-input',
            ]); ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($model, 'full_name')->textInput([
                'class' => 'ff-line-text-input',
            ]); ?>
        </div>
        <div class="col-md-6">
         <?= $form->field($model, 'last_name')->textInput([
             'class' => 'ff-line-text-input',
         ]); ?>
     </div>
    </div>
    <input type="button" class="btn btn-success" value="Add" id="save-ajax-guest" style="margin-bottom: 20px;">
<? ActiveForm::end(); ?>