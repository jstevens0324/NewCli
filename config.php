<?php
return array(
    'name'     => 'VetLogic CLI',
    'version'  => '1.0.0',
    'commands' => array(
        new \Application\Command\EmindersAppointments,
        new \Application\Command\EmindersAppointmentResponses,
        new \Application\Command\EmindersReminders,
        new \Application\Command\EmindersWinuserBatch,
        new \Application\Command\PetwiseAppointments,
        new \Application\Command\PetwiseBirthdays,
        new \Application\Command\PetwiseNewsletters,
        new \Application\Command\PetwiseReminders,
        new \Application\Command\PetwiseSender,
        new \Application\Command\PetwiseUserSync,
    ),

    'connection' => array(
        'test' => array(
            'dbname'   => 'vetlogic_message',
            'user'     => 'root',
            'password' => '',
            'host'     => 'localhost',
            'driver'   => 'pdo_mysql'
        ),
        'default' => array(
            'dbname'   => 'vetlogic_live',
            'user'     => 'vetlogic_live',
            'password' => '?yW13F{*=?',
            'host'     => 'mcallister.servers.deltasys.com',
            'driver'   => 'pdo_mysql'
        )
    ),
);