<?php

namespace App\Livewire;

use App\Models\VoiceMessage;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Tawkey — Push To Talk')]
class WalkieTalkie extends Component
{
    use WithFileUploads;

    public const CHANNELS = 10;

    /** The channel names / frequencies, indexed 1..10 */
    public array $channelMeta = [];

    public int $channel = 1;

    /** The recorded audio blob uploaded from the browser. */
    public $audio;

    public float $duration = 0;

    /** Highest voice-message id this client has already heard. */
    public int $lastSeenId = 0;

    public function mount(): void
    {
        $this->channelMeta = $this->buildChannelMeta();

        // Don't replay history on first load — start listening from "now".
        $this->lastSeenId = (int) VoiceMessage::max('id');
    }

    protected function buildChannelMeta(): array
    {
        $names = [
            'Orbit', 'Nebula', 'Pulsar', 'Quasar', 'Aurora',
            'Zenith', 'Vortex', 'Cosmos', 'Horizon', 'Infinity',
        ];

        $meta = [];
        foreach ($names as $i => $name) {
            $ch = $i + 1;
            $meta[$ch] = [
                'name' => $name,
                'freq' => number_format(88.1 + $i * 1.7, 1),
            ];
        }

        return $meta;
    }

    public function nextChannel(): void
    {
        $this->channel = $this->channel >= self::CHANNELS ? 1 : $this->channel + 1;
        $this->onChannelChanged();
    }

    public function prevChannel(): void
    {
        $this->channel = $this->channel <= 1 ? self::CHANNELS : $this->channel - 1;
        $this->onChannelChanged();
    }

    public function setChannel(int $channel): void
    {
        $this->channel = max(1, min(self::CHANNELS, $channel));
        $this->onChannelChanged();
    }

    protected function onChannelChanged(): void
    {
        // Tune in fresh — ignore anything already transmitted on this channel.
        $this->lastSeenId = (int) VoiceMessage::where('channel', $this->channel)->max('id');
        $this->dispatch('channel-tuned', channel: $this->channel);
    }

    /**
     * Persist a transmission recorded in the browser to DigitalOcean Spaces.
     */
    public function sendTransmission(): void
    {
        $this->validate([
            'audio' => ['required', 'file', 'max:12288'],
            'channel' => ['required', 'integer', 'min:1', 'max:'.self::CHANNELS],
        ]);

        $dir = 'talkie/ch'.$this->channel;
        $stem = now()->format('Ymd_His').'_'.Str::random(8);

        // iOS Safari can't decode WebM/Opus, so normalize every clip to MP3
        // (universally playable). Fall back to the raw upload if ffmpeg is
        // unavailable so the app still works on desktop.
        if ($mp3 = $this->transcodeToMp3($this->audio->getRealPath())) {
            $path = Storage::disk('spaces')->putFileAs($dir, new File($mp3), $stem.'.mp3', 'public');
            @unlink($mp3);
        } else {
            $extension = $this->audio->getClientOriginalExtension() ?: 'webm';
            $path = $this->audio->storeAs($dir, $stem.'.'.$extension, 'spaces');
        }

        $url = rtrim(config('filesystems.disks.spaces.url'), '/').'/'.$path;

        $message = VoiceMessage::create([
            'channel' => $this->channel,
            'path' => $path,
            'url' => $url,
            'duration' => round((float) $this->duration, 2),
            'callsign' => 'Voyager-'.strtoupper(Str::random(4)),
        ]);

        // We already heard our own transmission live — don't echo it back.
        $this->lastSeenId = $message->id;
        $this->audio = null;
        $this->duration = 0;

        $this->dispatch('transmission-sent');
    }

    /**
     * Transcode a recorded clip to mono MP3 via ffmpeg.
     * Returns the path to a temp .mp3 file, or null if ffmpeg isn't available.
     */
    protected function transcodeToMp3(string $inputPath): ?string
    {
        $output = tempnam(sys_get_temp_dir(), 'tawkey_').'.mp3';

        try {
            $result = Process::timeout(30)->run([
                'ffmpeg', '-y', '-i', $inputPath,
                '-vn', '-ac', '1', '-ar', '44100', '-b:a', '64k',
                $output,
            ]);
        } catch (\Throwable $e) {
            @unlink($output);

            return null;
        }

        if (! $result->successful() || ! is_file($output) || filesize($output) === 0) {
            @unlink($output);

            return null;
        }

        return $output;
    }

    /**
     * Polled by wire:poll — pushes any fresh transmissions to the browser.
     */
    public function checkForMessages(): void
    {
        $incoming = VoiceMessage::where('channel', $this->channel)
            ->where('id', '>', $this->lastSeenId)
            ->orderBy('id')
            ->get(['id', 'url', 'callsign', 'duration']);

        if ($incoming->isEmpty()) {
            return;
        }

        $this->lastSeenId = (int) $incoming->last()->id;

        // Play only the most recent so the queue never floods.
        $latest = $incoming->last();

        $this->dispatch('incoming-transmission',
            url: $latest->url,
            callsign: $latest->callsign,
            channel: $this->channel,
            duration: (float) $latest->duration,
        );
    }

    public function render()
    {
        return view('livewire.walkie-talkie');
    }
}
