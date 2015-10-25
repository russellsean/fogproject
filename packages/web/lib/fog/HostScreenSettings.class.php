<?php
class HostScreenSettings extends FOGController {
    protected $databaseTable = 'hostScreenSettings';
    protected $databaseFields = array(
        'id' => 'hssID',
        'hostID' => 'hssHostID',
        'width' => 'hssWidth',
        'height' => 'hssHeight',
        'refresh' => 'hssRefresh',
        'orientation' => 'hssOrientation',
        'other1' => 'hssOther1',
        'other2' => 'hssOther2',
    );
    protected $databaseFieldsRequired = array(
        'hostID',
    );
}
