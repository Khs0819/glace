<?php

namespace App\Services\Sms;

use RuntimeException;

/**
 * A minimal SMPP v3.4 client: bind, submit one message, unbind.
 *
 * SMPP is a binary protocol over a raw TCP socket. Every PDU is a 16-byte
 * header — length, command id, status, sequence — followed by a body of
 * C-strings and octets. There is no library here on purpose: the shop needs
 * `submit_sm` and nothing else, and that is a small, well-specified surface.
 *
 * Arabic forces one decision. GSM 03.38, the default alphabet, cannot express
 * it, so anything outside plain ASCII goes out as UCS-2 (data_coding 0x08),
 * which every operator supports and which halves the per-part limit from 160
 * characters to 70 — hence the concatenation below.
 *
 * The connection is opened per send and closed after. A pooled bind would be
 * fewer round trips, but a stale one that the SMSC has quietly dropped fails
 * exactly when a customer is waiting on a login code.
 */
class SmppClient
{
    // ─── PDU command ids ────────────────────────────────────────────────────

    private const BIND_TRANSMITTER      = 0x00000002;
    private const BIND_TRANSMITTER_RESP = 0x80000002;
    private const SUBMIT_SM             = 0x00000004;
    private const SUBMIT_SM_RESP        = 0x80000004;
    private const UNBIND                = 0x00000006;
    private const GENERIC_NACK          = 0x80000000;

    private const ESME_ROK = 0x00000000;

    /** The statuses the operator documented, so a failure reads as a sentence. */
    private const ERRORS = [
        0x00000003 => 'أمر غير مدعوم (ESME_RINVCMDID)',
        0x0000000A => 'رقم المرسل غير صالح (ESME_RINVSRCADR)',
        0x0000000B => 'رقم المستقبل غير صالح (ESME_RINVDSTADR)',
        0x0000000D => 'فشل تسجيل الدخول إلى مزوّد الرسائل (ESME_RBINDFAIL)',
        0x00000058 => 'مزوّد الرسائل يحد من معدل الإرسال (ESME_RTHROTTLED)',
    ];

    /** @var resource|null */
    private $socket = null;

    private int $sequence = 0;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config = []) {}

    public function configured(): bool
    {
        return filled($this->config['host'] ?? null)
            && filled($this->config['username'] ?? null);
    }

    /**
     * Send one message. Opens a bind, submits, unbinds.
     *
     * @throws RuntimeException on any failure the caller should know about
     */
    public function send(string $destination, string $message): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('إعدادات SMPP غير مكتملة');
        }

        $this->connect();

        try {
            $this->bind();

            $ids = [];

            foreach ($this->parts($message) as $index => $part) {
                $ids[] = $this->submit($destination, $part['text'], $part['udh']);
            }

            return implode(',', $ids);
        } finally {
            $this->disconnect();
        }
    }

    // ─── transport ──────────────────────────────────────────────────────────

    private function connect(): void
    {
        $errno  = 0;
        $errstr = '';

        $socket = @fsockopen(
            (string) $this->config['host'],
            (int) ($this->config['port'] ?? 9999),
            $errno,
            $errstr,
            (float) ($this->config['timeout'] ?? 15),
        );

        if ($socket === false) {
            throw new RuntimeException("تعذّر الاتصال بمزوّد الرسائل ({$errstr})");
        }

        stream_set_timeout($socket, (int) ($this->config['timeout'] ?? 15));

        $this->socket = $socket;
    }

    private function disconnect(): void
    {
        if ($this->socket === null) {
            return;
        }

        // Best effort: if the link is already gone, there is nothing to say
        // goodbye to and the send has either happened or not.
        try {
            $this->write($this->pdu(self::UNBIND, ''));
        } catch (RuntimeException) {
            //
        }

        fclose($this->socket);
        $this->socket = null;
    }

    private function bind(): void
    {
        // system_id, password, system_type, interface_version, ton, npi, range
        $body = $this->cString((string) $this->config['username'])
            . $this->cString((string) ($this->config['password'] ?? ''))
            . $this->cString((string) ($this->config['system_type'] ?? ''))
            . chr(0x34)     // SMPP v3.4
            . chr(0)        // addr_ton
            . chr(0)        // addr_npi
            . $this->cString('');

        $this->write($this->pdu(self::BIND_TRANSMITTER, $body));

        $response = $this->read();

        $this->assertOk($response, self::BIND_TRANSMITTER_RESP, 'فشل تسجيل الدخول إلى مزوّد الرسائل');
    }

    /**
     * @param  string  $udh  user-data header for a concatenated part, or ''
     */
    private function submit(string $destination, string $text, string $udh): string
    {
        $ucs2 = ! $this->isAscii($text);

        $payload = $udh . ($ucs2 ? $this->toUcs2($text) : $text);

        $body = $this->cString('')                                    // service_type
            . chr(5) . chr(0)                                          // source ton/npi (alphanumeric)
            . $this->cString((string) ($this->config['sender'] ?? '')) // source_addr
            . chr(1) . chr(1)                                          // dest ton/npi (international)
            . $this->cString($destination)
            // esm_class: 0x40 marks the body as carrying a UDH.
            . chr($udh === '' ? 0x00 : 0x40)
            . chr(0)                                                   // protocol_id
            . chr(0)                                                   // priority_flag
            . $this->cString('')                                       // schedule_delivery_time
            . $this->cString('')                                       // validity_period
            . chr(0)                                                   // registered_delivery
            . chr(0)                                                   // replace_if_present
            // 0x08 = UCS-2. GSM 03.38 has no Arabic, so anything non-ASCII
            // must go out this way or arrive as question marks.
            . chr($ucs2 ? 0x08 : 0x00)
            . chr(0)                                                   // sm_default_msg_id
            . chr(strlen($payload))
            . $payload;

        $this->write($this->pdu(self::SUBMIT_SM, $body));

        $response = $this->read();

        $this->assertOk($response, self::SUBMIT_SM_RESP, 'رفض مزوّد الرسائل إرسال الرسالة');

        // The body of submit_sm_resp is a single C-string: the message id.
        return rtrim(substr($response['body'], 0, strpos($response['body'], "\0") ?: null), "\0");
    }

    // ─── PDUs ───────────────────────────────────────────────────────────────

    private function pdu(int $command, string $body): string
    {
        $this->sequence++;

        return pack('NNNN', 16 + strlen($body), $command, 0, $this->sequence) . $body;
    }

    private function write(string $bytes): void
    {
        $written = 0;
        $length  = strlen($bytes);

        while ($written < $length) {
            $chunk = @fwrite($this->socket, substr($bytes, $written));

            if ($chunk === false || $chunk === 0) {
                throw new RuntimeException('انقطع الاتصال بمزوّد الرسائل');
            }

            $written += $chunk;
        }
    }

    /** @return array{length: int, command: int, status: int, sequence: int, body: string} */
    private function read(): array
    {
        $header = $this->readBytes(16);

        /** @var array{length: int, command: int, status: int, sequence: int} $fields */
        $fields = unpack('Nlength/Ncommand/Nstatus/Nsequence', $header);

        // A length below the header size, or absurdly large, means the stream
        // is out of sync — reading further would block on garbage.
        if ($fields['length'] < 16 || $fields['length'] > 65536) {
            throw new RuntimeException('رد غير صالح من مزوّد الرسائل');
        }

        return $fields + ['body' => $this->readBytes($fields['length'] - 16)];
    }

    private function readBytes(int $length): string
    {
        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = @fread($this->socket, $length - strlen($buffer));

            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->socket);

                throw new RuntimeException($meta['timed_out'] ?? false
                    ? 'انتهت مهلة انتظار الرد من مزوّد الرسائل'
                    : 'انقطع الاتصال بمزوّد الرسائل');
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    /** @param array{command: int, status: int, body: string} $response */
    private function assertOk(array $response, int $expected, string $context): void
    {
        if ($response['command'] === self::GENERIC_NACK) {
            throw new RuntimeException($context . ' — رفض عام من المزوّد');
        }

        if ($response['status'] !== self::ESME_ROK) {
            $detail = self::ERRORS[$response['status']]
                ?? sprintf('رمز الخطأ 0x%08X', $response['status']);

            throw new RuntimeException($context . ' — ' . $detail);
        }

        if ($response['command'] !== $expected) {
            throw new RuntimeException($context . ' — رد غير متوقع من المزوّد');
        }
    }

    // ─── encoding ───────────────────────────────────────────────────────────

    private function cString(string $value): string
    {
        return $value . "\0";
    }

    private function isAscii(string $text): bool
    {
        return preg_match('/^[\x20-\x7E\r\n]*$/', $text) === 1;
    }

    private function toUcs2(string $text): string
    {
        $encoded = @iconv('UTF-8', 'UCS-2BE', $text);

        if ($encoded === false) {
            throw new RuntimeException('تعذّر ترميز نص الرسالة');
        }

        return $encoded;
    }

    /**
     * Split a message into deliverable parts.
     *
     * One SMS carries 140 octets. UCS-2 spends two per character, so 70; a
     * concatenated part gives up six of those to the UDH that tells the handset
     * how to reassemble it, leaving 67.
     *
     * @return array<int, array{text: string, udh: string}>
     */
    private function parts(string $message): array
    {
        $ucs2  = ! $this->isAscii($message);
        $limit = $ucs2 ? 70 : 160;

        if (mb_strlen($message) <= $limit) {
            return [['text' => $message, 'udh' => '']];
        }

        $perPart = $ucs2 ? 67 : 153;
        $chunks  = [];

        for ($i = 0; $i < mb_strlen($message); $i += $perPart) {
            $chunks[] = mb_substr($message, $i, $perPart);
        }

        // One reference ties the parts together on the handset; a collision
        // would interleave two messages, so it is random per message.
        $reference = random_int(0, 255);
        $total     = count($chunks);

        return array_map(fn (string $chunk, int $index) => [
            'text' => $chunk,
            // 05 00 03 <ref> <total> <seq> — the concatenation IE.
            'udh'  => chr(5) . chr(0) . chr(3) . chr($reference) . chr($total) . chr($index + 1),
        ], $chunks, array_keys($chunks));
    }
}
