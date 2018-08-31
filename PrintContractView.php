<?php
include_once "IPrint.php";
include_once "M_PDO.php";

class PrintContractView implements IPrint
{
    protected $_DB;
    protected $_customer;

    public function __construct($customer)
    {
        $this->_DB = M_PDO::Instance();
        $this->_customer = $customer;
    }

    public function getContract()
    {
        $customer = $this->_customer['customer'];
        $allRecords = $this->_DB->Select("SELECT *
            FROM obj_contracts
            JOIN obj_customers ON obj_contracts.id_customer = obj_customers.id_customer
            JOIN obj_services os on os.id_contract = obj_contracts.id_contract
            WHERE obj_contracts.id_customer = (
                    SELECT id_customer
                    FROM obj_customers
                    WHERE id_customer = '{$customer}' OR name_customer = '{$customer}'
                )
            ");

        $status = $this->getServicesStatus($this->_customer);

        if($status){
            $allRecords = $this->getContractsWithServices($status, $allRecords);
        }

        $services = $this->_getServices($allRecords);
        $this->renderContract($services);
    }

    protected function getContractsWithServices($status, $records)
    {
        if(count($records) > 0){
            return $this->_checkServices($status, $records);
        } else {
            return 0;
        }
    }

    public function renderContract($arrays)
    {
        require_once "views/contracts-table.php";
    }

    private function _checkServices($status, $records)
    {
        foreach ($records as $id => $record){
            if(!in_array($record['status'], $status)){
                unset($records[$id]);
            }
        }
        return $records;
    }

    private function _getServices($array)
    {

        $main = [];
        if(is_array($array)){
            foreach ($array as $id => $status){

                if(count($main) == 0 ){
                    $status['statusAll'][0] = [
                        'title_service' => $status['title_service'],
                        'status' => $status['status']
                    ];
                    $main[] = $status;
                } elseif (count($main) > 0){
                    foreach ($main as $search_id => $search){
                        if ($search['id_contract'] == $status['id_contract']) {

                            $stat = [
                                'title_service' => $status['title_service'],
                                'status' => $status['status']
                            ];
                            array_push($main[$search_id]['statusAll'], $stat);

                        } else {
                            $main[] = $status;
                        }
                    }
                }
            }
            return $main;
        }

    }

    protected function getServicesStatus($records)
    {
        if(is_array($records)){
            return array_intersect_key($this->_search_status, $records);
        }
        return false;

    }

    private $_search_status = [
        'work' => 'work',
        'connecting' => 'connecting',
        'disconnected' => 'disconnected',
    ];
}