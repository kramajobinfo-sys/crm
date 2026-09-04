// Emails an encrypted backup as an attachment via the app's configured SMTP mailer.
// Values come from env (BK_FILE, BK_TO, BK_NAME, BK_MSG) so nothing is string-interpolated.
$f = getenv('BK_FILE'); $to = getenv('BK_TO'); $n = getenv('BK_NAME');
$msg = getenv('BK_MSG') ?: 'NPCRM encrypted backup attached.';
try {
    \Illuminate\Support\Facades\Mail::raw($msg, function ($m) use ($to, $f, $n) {
        $m->to($to)->subject('NPCRM offsite backup '.$n)
          ->attach($f, ['as' => $n, 'mime' => 'application/octet-stream']);
    });
    echo 'EMAIL_OK'.PHP_EOL;
} catch (\Throwable $e) {
    echo 'EMAIL_ERR '.$e->getMessage().PHP_EOL;
}
