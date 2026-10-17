<?php
return [
    // Do not enable until trusted endpoints and integration tests are validated.
    'enabled' => false,
    'bsc_rpc_url' => env('LEOBOT_BSC_RPC_URL', ''),
    'bsc_confirmations' => 30,
    'tron_full_node_url' => env('LEOBOT_TRON_FULL_NODE_URL', ''),
    'tron_solidity_node_url' => env('LEOBOT_TRON_SOLIDITY_NODE_URL', ''),
];
