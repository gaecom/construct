<?php namespace JonathanTorres\Construct;

use Illuminate\Filesystem\Filesystem;
use JonathanTorres\Construct\Commands\Settings\Construct as CommandSettings;

class Construct
{

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     **/
    protected $file;

    /**
     * String helper.
     *
     * @var \JonathanTorres\Construct\Str
     **/
    protected $str;

    /**
     * The construct command selctions instance.
     *
     * @var \JonathanTorres\Construct\Commands\Settings\Construct
     **/
    protected $commandSettings;

    /**
     * Folder to store source files.
     *
     * @var string
     **/
    protected $srcPath = 'src';

    /**
     * The files to ignore on exporting.
     *
     * @var array
     **/
    protected $exportIgnores = [];

    /**
     * The selected testing framework version.
     *
     * @var string
     **/
    protected $testingVersion;

    /**
     * Camel case version of vendor name.
     * ex: JonathanTorres
     *
     * @var string
     **/
    protected $vendorUpper;

    /**
     * Lower case version of vendor name.
     * ex: jonathantorres
     *
     * @var string
     **/
    protected $vendorLower;

    /**
     * Camel case version of project name.
     * ex: Construct
     *
     * @var string
     **/
    protected $projectUpper;

    /**
     * Lower case version of project name.
     * ex: construct
     *
     * @var string
     **/
    protected $projectLower;

    /**
     * Initialize.
     *
     * @param \Illuminate\Filesystem\Filesystem $file
     * @param \JonathanTorres\Construct\Str     $str
     *
     * @return void
     **/
    public function __construct(Filesystem $file, Str $str)
    {
        $this->file = $file;
        $this->str = $str;
    }

    /**
     * Generate project.
     *
     * @param CommandSettings $settings The command settings made by the user.
     *
     * @return void
     **/
    public function generate(CommandSettings $commandSettings)
    {
        $this->commandSettings = $commandSettings;

        $this->saveNames();
        $this->root();
        $this->src();
        $this->docs();
        $this->testing();
        $this->gitignore();

        if ($this->commandSettings->withPhpcsConfiguration()) {
            $this->phpcs();
        }

        $this->travis();
        $this->license();
        $this->composer();
        $this->projectClass();
        $this->projectTest();

        $this->gitattributes();
        $this->composerInstall();

        if ($this->commandSettings->withGitInit()) {
            $this->gitInit();
        }
    }

    /**
     * Save versions of project names.
     *
     * @return void
     **/
    protected function saveNames()
    {
        $names = $this->str->split($this->commandSettings->getProjectName());

        $this->vendorLower = $this->str->toLower($names['vendor']);
        $this->vendorUpper = $this->str->toStudly($names['vendor']);
        $this->projectLower = $this->str->toLower($names['project']);
        $this->projectUpper = $this->str->toStudly($names['project']);
    }

    /**
     * Generate files for the selected testing framework.
     *
     * @return void
     **/
    protected function testing()
    {
        switch ($this->commandSettings->getTestingFramework()) {
            case 'phpunit':
                $this->phpunit();
                break;

            case 'behat':
                $this->behat();
                break;

            case 'phpspec':
                $this->phpspec();
                break;

            case 'codeception':
                $this->codeception();
                break;

            default:
                $this->phpunit();
                break;
        }
    }

    /**
     * Create project root folder.
     *
     * @return void
     **/
    protected function root()
    {
        $this->file->makeDirectory($this->projectLower);
    }

    /**
     * Create 'src' folder.
     *
     * @return void
     **/
    protected function src()
    {
        $this->file->makeDirectory($this->projectLower . '/' . $this->srcPath);
    }

    /**
     * Generate gitignore file.
     *
     * @return void
     **/
    protected function gitignore()
    {
        $this->file->copy(__DIR__ . '/stubs/gitignore.txt', $this->projectLower . '/' . '.gitignore');
        $this->exportIgnores[] = '.gitignore';
    }

    /**
     * Generate gitattributes file.
     *
     * @return void
     **/
    protected function gitattributes()
    {
        $this->exportIgnores[] = '.gitattributes';
        sort($this->exportIgnores);

        $content = $this->file->get(__DIR__ . '/stubs/gitattributes.txt');

        foreach ($this->exportIgnores as $ignore) {
            $content .= PHP_EOL . '/' . $ignore . ' export-ignore';
        }

        $this->file->put($this->projectLower . '/' . '.gitattributes', $content);
    }

    /**
     * Initialize an empty git repo.
     *
     * @return void
     **/
    protected function gitInit()
    {
        if (is_dir($this->projectLower)) {
            $command = 'cd ' . $this->projectLower . ' && git init';
            exec($command);
        }
    }

    /**
     * Generate PHP CS Fixer configuration file.
     *
     * @return void
     **/
    protected function phpcs()
    {
        $this->file->copy(
            __DIR__ . '/stubs/phpcs.txt',
            $this->projectLower . '/' . '.php_cs'
        );
        $this->exportIgnores[] = '.php_cs';
    }

    /**
     * Generate documentation (README, CONTRIBUTING, CHANGELOG) files.
     *
     * @return void
     */
    protected function docs()
    {
        $readmeFile = $this->file->get(__DIR__ . '/stubs/README.txt');
        $readmeContent = str_replace(
            [
                '{project_upper}',
                '{license}',
                '{vendor_lower}',
                '{project_lower}'
            ],
            [
                $this->projectUpper,
                $this->commandSettings->getLicense(),
                $this->vendorLower,
                $this->projectLower
            ],
            $readmeFile
        );
        $this->file->put($this->projectLower . '/' . 'README.md', $readmeContent);

        $contributingContent = $this->file->get(__DIR__ . '/stubs/CONTRIBUTING.txt');
        $this->file->put($this->projectLower . '/' . 'CONTRIBUTING.md', $contributingContent);

        $changelogFile = $this->file->get(__DIR__ . '/stubs/CHANGELOG.txt');
        $changelogContent = str_replace(
            '{creation_date}',
            (new \DateTime())->format('Y-m-d'),
            $changelogFile
        );
        $this->file->put($this->projectLower . '/' . 'CHANGELOG.md', $changelogContent);

        $this->exportIgnores[] = 'README.md';
        $this->exportIgnores[] = 'CONTRIBUTING.md';
        $this->exportIgnores[] = 'CHANGELOG.md';
    }

    /**
     * Generate phpunit file/settings.
     *
     * @return void
     **/
    protected function phpunit()
    {
        $this->testingVersion = '4.6.*';

        $file = $this->file->get(__DIR__ . '/stubs/phpunit.txt');
        $content = str_replace('{project_upper}', $this->projectUpper, $file);

        $this->file->put($this->projectLower . '/' . 'phpunit.xml.dist', $content);
        $this->exportIgnores[] = 'phpunit.xml.dist';
    }

    /**
     * Tries to determine the configured git user, returns a default when failing to do so.
     *
     * @return array
     */
    protected function determineConfiguredGitUser()
    {
        $user = [
            'name' => 'Some name',
            'email' => 'some@email.com'
        ];

        $command = 'git config --get-regexp "^user.*"';
        exec($command, $keyValueLines, $returnValue);

        if ($returnValue === 0) {
            foreach ($keyValueLines as $keyValueLine) {
                list($key, $value) = explode(' ', $keyValueLine, 2);
                $key = str_replace('user.', '', $key);
                $user[$key] = $value;
            }
        }

        return $user;
    }

    /**
     * Construct a correct project namespace name.
     *
     * @param boolean $useDoubleSlashes Whether or not to create the namespace with double slashes \\
     *
     * @return string
     **/
    protected function createNamespace($useDoubleSlashes = false)
    {
        $namespace = $this->commandSettings->getNamespace();
        $projectName = $this->commandSettings->getProjectName();

        if ($namespace === 'Vendor\Project' || $namespace === $projectName) {
            return $this->str->createNamespace($namespace, true, $useDoubleSlashes);
        }

        return $this->str->createNamespace($namespace, false, $useDoubleSlashes);
    }

    /**
     * Generate phpspec file/settings.
     *
     * @return void
     **/
    protected function phpspec()
    {
        $this->testingVersion = '~2.0';
    }

    /**
     * Generate behat file/settings.
     *
     * @return void
     **/
    protected function behat()
    {
        $this->testingVersion = '~3.0';
    }

    /**
     * Generate codeception file/settings.
     *
     * @return void
     **/
    protected function codeception()
    {
        $this->testingVersion = '2.0.*';
    }

    /**
     * Generate .travis.yml file.
     *
     * @return void
     **/
    protected function travis()
    {
        $file = $this->file->get(__DIR__ . '/stubs/travis.txt');
        $content = str_replace(
            '{testing}',
            $this->commandSettings->getTestingFramework(),
            $file
        );

        $this->file->put($this->projectLower . '/' . '.travis.yml', $content);
        $this->exportIgnores[] = '.travis.yml';
    }

    /**
     * Generate LICENSE.md file.
     *
     * @return void
     */
    protected function license()
    {
        $file = $this->file->get(
            __DIR__ . '/stubs/licenses/' . strtolower($this->commandSettings->getLicense()) . '.txt'
        );

        $user = $this->determineConfiguredGitUser();

        $content = str_replace(
            ['{year}', '{author_name}'],
            [(new \DateTime())->format('Y'), $user['name']],
            $file
        );

        $this->file->put($this->projectLower . '/' . 'LICENSE.md', $content);
        $this->exportIgnores[] = 'LICENSE.md';
    }

    /**
     * Generate composer file.
     *
     * @return void
     **/
    protected function composer()
    {
        $file = $this->file->get(__DIR__ . '/stubs/composer.txt');
        $user = $this->determineConfiguredGitUser();

        $stubs = [
            '{project_upper}',
            '{project_lower}',
            '{vendor_lower}',
            '{vendor_upper}',
            '{testing}',
            '{testing_version}',
            '{namespace}',
            '{license}',
            '{author_name}',
            '{author_email}',
        ];

        $values = [
            $this->projectUpper,
            $this->projectLower,
            $this->vendorLower,
            $this->vendorUpper,
            $this->commandSettings->getTestingFramework(),
            $this->testingVersion,
            $this->createNamespace(true),
            $this->commandSettings->getLicense(),
            $user['name'],
            $user['email'],
        ];

        $content = str_replace($stubs, $values, $file);

        $this->file->put($this->projectLower . '/' . 'composer.json', $content);
    }

    /**
     * Do an initial composer install in constructed project.
     *
     * @return void
     */
    protected function composerInstall()
    {
        if (is_dir($this->projectLower)) {
            $command = 'cd ' . $this->projectLower . ' && composer install';
            exec($command);
        }
    }

    /**
     * Generate project class file.
     *
     * @return void
     **/
    protected function projectClass()
    {
        $file = $this->file->get(__DIR__ . '/stubs/Project.txt');

        $stubs = [
            '{project_upper}',
            '{vendor_upper}',
            '{namespace}',
        ];

        $values = [
            $this->projectUpper,
            $this->vendorUpper,
            $this->createNamespace()
        ];

        $content = str_replace($stubs, $values, $file);

        $this->file->put($this->projectLower . '/' . $this->srcPath . '/' . $this->projectUpper . '.php', $content);
    }

    /**
     * Generate project test file.
     *
     * @return void
     **/
    protected function projectTest()
    {
        $file = $this->file->get(__DIR__ . '/stubs/ProjectTest.txt');

        $stubs = [
            '{project_upper}',
            '{project_camel_case}',
            '{vendor_upper}',
            '{namespace}',
        ];

        $values = [
            $this->projectUpper,
            $this->str->toCamelCase($this->projectLower),
            $this->vendorUpper,
            $this->createNamespace(),
        ];

        $content = str_replace($stubs, $values, $file);

        $this->file->makeDirectory($this->projectLower . '/tests');
        $this->file->put($this->projectLower . '/tests/' . $this->projectUpper . 'Test.php', $content);
        $this->exportIgnores[] = 'tests';
    }
}
