<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Commands;

use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Gradient;
use Gabrielesbaiz\QrcodeToolkit\Options;
use Gabrielesbaiz\QrcodeToolkit\QrManager;
use Gabrielesbaiz\QrcodeToolkit\Support\Encoder;
use Illuminate\Console\Command;
use Throwable;

/**
 * List the configured profiles, and draw one in the terminal.
 *
 * A profile is a set of numbers in a config file until you see what it
 * produces, and the terminal can show a QR code perfectly well: two
 * block characters per module keeps the aspect ratio square.
 */
class ProfilesQrCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'qr:profiles
        {profile? : Draw this profile rather than listing them all}
        {--content=https://laravel.com : What to encode in the preview}
        {--no-preview : List the settings without drawing anything}';

    /**
     * The console command description.
     */
    protected $description = 'List the configured QR profiles, and preview one in the terminal';

    /**
     * Execute the console command.
     */
    public function handle(QrManager $manager): int
    {
        /** @var array<string, array<string, mixed>> $profiles */
        $profiles = config('qrcode-toolkit.profiles', []);

        if ($profiles === []) {
            $this->components->warn('No profiles are configured. Add them under qrcode-toolkit.profiles.');

            return self::SUCCESS;
        }

        $only = $this->argument('profile');

        if (is_string($only) && $only !== '') {
            if (! array_key_exists($only, $profiles)) {
                $this->components->error(sprintf(
                    'Unknown profile [%s]. Defined: %s.',
                    $only,
                    implode(', ', array_keys($profiles)),
                ));

                return self::FAILURE;
            }

            $profiles = [$only => $profiles[$only]];
        }

        $content = (string) $this->option('content');

        foreach ($profiles as $name => $settings) {
            $this->newLine();
            $this->line('  <options=bold>'.$name.'</>');

            try {
                $options = $manager->profile($name, $content)->options();
            } catch (QrException $exception) {
                $this->components->error($exception->getMessage());

                continue;
            }

            $this->components->twoColumnDetail('Size', $options->size.' px');
            $this->components->twoColumnDetail('Error correction', $options->ecc->value);
            $this->components->twoColumnDetail('Modules', $options->modules->value.' at '.$options->moduleScale);
            $this->components->twoColumnDetail('Eyes', $options->eyes->value);
            $this->components->twoColumnDetail('Foreground', $options->foreground);
            $this->components->twoColumnDetail('Background', $options->background ?? 'transparent');

            if ($options->gradient instanceof Gradient) {
                $this->components->twoColumnDetail(
                    'Gradient',
                    sprintf('%s, %s to %s', $options->gradient->type->value, $options->gradient->from, $options->gradient->to),
                );
            }

            if ($this->option('no-preview') !== true) {
                $this->newLine();
                $this->drawInTerminal($content, $options);
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Draw the symbol with block characters.
     *
     * Two columns per module, because a terminal cell is about half as
     * wide as it is tall and a code drawn one cell per module comes out
     * squashed and unreadable to a phone held up to the screen.
     */
    protected function drawInTerminal(string $content, Options $options): void
    {
        try {
            $matrix = Encoder::encode($content, $options);
        } catch (Throwable $exception) {
            $this->components->warn('Could not draw a preview: '.$exception->getMessage());

            return;
        }

        $size = $matrix->size();
        $quiet = 2;
        $width = ($size + $quiet * 2) * 2;

        $blank = str_repeat(' ', $width);

        for ($i = 0; $i < $quiet; $i++) {
            $this->line('<bg=white>'.$blank.'</>');
        }

        for ($y = 0; $y < $size; $y++) {
            $row = str_repeat(' ', $quiet * 2);

            for ($x = 0; $x < $size; $x++) {
                $row .= $matrix->isDark($x, $y)
                    ? '</><bg=black>  </><bg=white>'
                    : '  ';
            }

            $this->line('<bg=white>'.$row.str_repeat(' ', $quiet * 2).'</>');
        }

        for ($i = 0; $i < $quiet; $i++) {
            $this->line('<bg=white>'.$blank.'</>');
        }
    }
}
