<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-03-17
 * Time: 11:40
 */

namespace inhere\console;

use inhere\console\io\Input;
use inhere\console\io\InputDefinition;
use inhere\console\io\Output;
use inhere\console\traits\InputOutputTrait;
use inhere\console\traits\UserInteractTrait;
use inhere\console\utils\Annotation;

/**
 * Class AbstractCommand
 * @package inhere\console
 */
abstract class AbstractCommand
{
    use InputOutputTrait;
    use UserInteractTrait;

    // command description message
    // please use the const setting current controller/command description
    const DESCRIPTION = '';

    // name -> {$name}
    const ANNOTATION_VAR = '{$%s}';

    /**
     * TODO ...
     * command description message
     * please use the property setting current controller/command description
     * @var string
     */
    public static $description = '';

    /**
     * command name e.g 'test' 'test:one'
     * @var string
     */
    public static $name = '';

    /**
     * Allow display message tags in the command annotation
     * @var array
     */
    protected static $allowTags = [
        // tag name => multi line align
        'description' => false,
        'usage' => false,
        'arguments' => true,
        'options' => true,
        'example' => true,
    ];

    /**
     * @var InputDefinition
     */
    private $definition;

    ////// for strict mode //////

    /**
     * Command constructor.
     * @param Input $input
     * @param Output $output
     * @param InputDefinition|null $definition
     */
    public function __construct(Input $input, Output $output, InputDefinition $definition = null)
    {
        $this->input = $input;
        $this->output = $output;

        if (null === $definition) {
            $this->definition = new InputDefinition();
        } else {
            $this->definition = $definition;
            $this->validate();
        }
    }

    /**
     * @return array
     */
    protected function configure()
    {
        return [
            // 'arguments' => [],
            // 'options' => [],
        ];
    }

    public function validate()
    {
        $definition = $this->definition;
        $givenArguments = $this->input->getArgs();

        $missingArguments = array_filter(array_keys($definition->getArguments()), function ($name) use ($definition, $givenArguments) {
            return !array_key_exists($name, $givenArguments) && $definition->argumentIsRequired($name);
        });

        if (count($missingArguments) > 0) {
            throw new \RuntimeException(sprintf('Not enough arguments (missing: "%s").', implode(', ', $missingArguments)));
        }
    }

    /**
     * run
     */
    abstract public function run();

    /**
     * beforeRun
     */
    protected function beforeRun()
    {
    }

    /**
     * afterRun
     */
    protected function afterRun()
    {
    }

    /**
     * 为命令注解提供可解析解析变量. 可以在命令的注释中使用
     * @return array
     */
    protected function annotationVars()
    {
        // e.g: `more info see {$name}/index`
        return [
            'command' => $this->input->getCommand(),
            'name' => static::$name,
        ];
    }

    /**
     * show help by parse method annotation
     * @param string    $method
     * @param null|string $action
     * @return int
     */
    protected function showHelpByMethodAnnotation($method, $action = null)
    {
        $ref = new \ReflectionClass($this);
        $cName = lcfirst(self::getName() ?: $ref->getShortName());

        if (!$ref->hasMethod($method) || !$ref->getMethod($method)->isPublic()) {
            $name = $action ? "$cName/$action" : $cName;
            $this->write("Command [<info>$name</info>] don't exist or don't allow access in the class.");

            return 0;
        }

        $m = $ref->getMethod($method);
        $tags = Annotation::tagList($m->getDocComment());

        foreach ($tags as $tag => $msg) {
            if (!is_string($msg)) {
                continue;
            }

            if (isset(self::$allowTags[$tag])) {
                // need multi align
                if (self::$allowTags[$tag]) {
                    $lines = array_map(function ($line) {
                        return trim($line);
                    }, explode("\n", $msg));

                    $msg = implode("\n  ", array_filter($lines, 'trim'));
                }

                $tag = ucfirst($tag);
                $this->write("<comment>$tag:</comment>\n  $msg\n");
            }
        }

        return 0;
    }

    /**
     * handle action/command runtime exception
     *
     * @param  \Throwable $e
     * @throws \Throwable
     */
    protected function handleRuntimeException(\Throwable $e)
    {
        throw $e;
    }

    /**
     * @param string $name
     */
    public static function setName(string $name)
    {
        static::$name = $name;
    }

    /**
     * @return string
     */
    public static function getName(): string
    {
        return static::$name;
    }

    /**
     * @return array
     */
    public static function getAllowTags(): array
    {
        return self::$allowTags;
    }

    /**
     * @param array $allowTags
     */
    public static function setAllowTags(array $allowTags)
    {
        self::$allowTags = $allowTags;
    }

    /**
     * @return string
     */
    public static function getDescription(): string
    {
        return self::$description;
    }

    /**
     * @param string $description
     */
    public static function setDescription(string $description)
    {
        self::$description = $description;
    }

    /**
     * @return InputDefinition
     */
    public function getDefinition(): InputDefinition
    {
        return $this->definition;
    }

    /**
     * @param InputDefinition $definition
     */
    public function setDefinition(InputDefinition $definition)
    {
        $this->definition = $definition;
    }


}
