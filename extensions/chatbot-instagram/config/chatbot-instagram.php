<?php

return [
    'name'    => 'Instagram Chatbot',
    'version' => '1.0.0',

    'instagram' => [
        'app_id'       => 'INSTAGRAM_APP_ID',
        'app_secret'   => 'INSTAGRAM_APP_SECRET',
        'base_url'     => 'https://www.facebook.com',
        'api_url'      => 'https://graph.facebook.com',
        'redirect_uri' => '/chatbot/instagram/oauth/callback',
        'api_version'  => 'v18.0',
        'scopes'       => [
            'instagram_basic',
            'instagram_content_publish',
            'pages_read_engagement',
            'pages_show_list',
            'business_management',
            'instagram_manage_insights',
            'instagram_manage_messages',
        ],
    ],
];
