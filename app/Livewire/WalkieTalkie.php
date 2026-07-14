<?php

namespace App\Livewire;

use App\Models\VoiceMessage;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Talkie — Push To Talk')]
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

        $extension = $this->audio->getClientOriginalExtension() ?: 'webm';
        $filename = 'ch'.$this->channel.'/'.now()->format('Ymd_His').'_'.Str::random(8).'.'.$extension;

        $path = $this->audio->storeAs('talkie', $filename, 'spaces');
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
