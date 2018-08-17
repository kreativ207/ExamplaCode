<?php

use app\components\widget\box\Box;
use app\models\room\PetsEnum;
//use kartik\checkbox\CheckboxX;
use yii\db\Query;
/** @var Room $modelRoom */

if($roomId):
$modelRoom = (new Query())->select(['*'])->from('vr_room')->where(['id' => (int)$roomId])->one();
//var_dump($modelRoom);
    if($modelRoom['pet_max_amount']){
        $pet_max_amount = $modelRoom['pet_max_amount'];
    } else {
        $pet_max_amount = 0;
    }
    if($modelRoom['pet_friendly'] > 0 && $modelRoom['pet_type'] != NULL):
?>
<?php Box::begin([
'header' => 'Pets',
]) ?>
<script>
    $('.petsnodisplay').removeClass('petsnodisplay');
    $('#petsdetail-adjustment').removeClass('petsnodisplay');
    $('#petsdetail').removeClass('petsnodisplay');
    $('#petsdetail-head').removeClass('petsnodisplay');
</script>
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
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<span class="errorPetsCount" style="color: red"></span>
<?php Box::end() ?>
