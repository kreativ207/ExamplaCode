<?php

namespace app\models\guest;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "{{%guest}}".
 *
 * @property integer $id
 * @property string $full_name
 * @property string $name_prefix
 * @property string $last_name
 * @property string $name_suffix
 * @property string $phones
 * @property integer $language_id
 * @property string $notes
 * @property string $email
 * @property integer $addresses
 * @property integer $rating
 * @property integer $user_id
 * @property string $another_users_id
 *
 */
class Guest extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%guest}}';
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['email','id'],'unique'],
            [['email'],'email'],
            //[['email', 'full_name', 'last_name'] , 'required'],
            [['full_name', 'last_name'] , 'required'],
            [['language_id',], 'integer'],
            [['full_name', 'name_prefix', 'last_name', 'name_suffix', 'phones', 'notes', 'email',], 'string', 'max' => 255],
            [['addresses', 'user_id', 'rating', 'another_users_id'], 'safe'],
            //['phones', 'match', 'pattern' => '/^[-+0-9]+$/i'],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name_prefix' => 'Name Prefix',
            'full_name' => 'First Name',
            'last_name' => 'Last Name',
            'name_suffix' => 'Name Suffix',
            'phones' => 'Telephone Number',
            'language_id' => 'Language',
            'notes' => 'Notes',
            'rating' => 'Rating',
        ];
    }
    
    /**
     * @inheritdoc
     * @return GuestQuery the active query used by this AR class.
     */
    public static function find()
    {
        return (new GuestQuery(get_called_class()))->byCurrentUser();
    }
    
    public function setAddresses($addresses)
    {
        $addresses = array_filter($addresses, function ($value) {
            return $value['address1'] || $value['address2'] || $value['state'] || $value['city'] || $value['zip'] || $value['country_id'];
        });
        $this->addresses = json_encode($addresses);
    }
    
    public function setPhones($phones)
    {
        $phones = array_filter($phones, function ($value) {
            return $value;
        });
        $phones = array_values($phones);
        if ($phones) {
            $this->phones = json_encode($phones);
        } else {
            $this->phones = null;
        }
        
    }
    
    public function getShortInfo()
    {
        if ($this->getAddresses()) {
            $shortInfoArray = [
                $this->getPhones() ? $this->getPhones()[0] : '',
                $this->email,
                $this->getAddresses()[0]['address1'] ? $this->getAddresses()[0]['address1'] : '',
                $this->getAddresses()[0]['address2'] ? $this->getAddresses()[0]['address2'] : '',
                $this->getAddresses()[0]['city'] ? $this->getAddresses()[0]['city'] : '',
                $this->getAddresses()[0]['state'] ? $this->getAddresses()[0]['state'] : '',
                $this->getAddresses()[0]['zip'] ? $this->getAddresses()[0]['zip'] : '',
            ];
            
        } else {
            $shortInfoArray = [
                $this->getPhones() ? $this->getPhones()[0] : '',
                $this->email,
            ];
        }
        return implode(', ', $shortInfoArray);
        
    }
    
    public function getAddresses()
    {
        if (is_array($this->addresses)) {
            return $this->addresses;
        }
        return json_decode($this->addresses, true);
    }
    
    public function getPhones()
    {
        if (!$this->phones) {
            return [];
        }
        if (is_array($this->phones)) {
            return $this->phones;
        }
        return json_decode($this->phones, true);
    }
}
