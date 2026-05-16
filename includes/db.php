<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/supabase.php';

$sb = new Supabase(SUPABASE_URL, SUPABASE_KEY);
