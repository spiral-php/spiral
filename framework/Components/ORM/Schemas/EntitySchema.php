<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Components\ORM\Schemas;

use Doctrine\Common\Inflector\Inflector;
use Spiral\Components\DBAL\Schemas\AbstractColumnSchema;
use Spiral\Components\DBAL\Schemas\AbstractTableSchema;
use Spiral\Components\DBAL\SqlFragmentInterface;
use Spiral\Components\I18n\Translator;
use Spiral\Components\ORM\Entity;
use Spiral\Components\ORM\ORMException;
use Spiral\Components\ORM\SchemaBuilder;
use Spiral\Core\Component;

class EntitySchema extends Component
{
    /**
     * Logging.
     */
    use Component\LoggerTrait;

    /**
     * Entity model class name.
     *
     * @var string
     */
    protected $class = '';

    /**
     * Parent ORM schema holds all other entities schema.
     *
     * @invisible
     * @var SchemaBuilder
     */
    protected $ormSchema = null;

    /**
     * Entity model reflection.
     *
     * @invisible
     * @var null|\ReflectionClass
     */
    protected $reflection = null;

    /**
     * Cache to speed up schema building.
     *
     * @invisible
     * @var array
     */
    protected $propertiesCache = array();

    /**
     * Table schema used to fetch information about declared or fetched columns. Empty if entity is
     * abstract.
     *
     * @var AbstractTableSchema
     */
    protected $tableSchema = null;

    /**
     * Model relationships.
     *
     * @var array
     */
    protected $relations = array();

    /**
     * New EntitySchema instance, schema responsible for detecting relationships, columns and indexes.
     * This class is really similar to DocumentSchema and can be merged into common parent in future.
     *
     * @param string        $class     Class name.
     * @param SchemaBuilder $ormSchema Parent ORM schema (all other documents).
     */
    public function __construct($class, SchemaBuilder $ormSchema)
    {
        $this->class = $class;
        $this->ormSchema = $ormSchema;
        $this->reflection = new \ReflectionClass($class);

        $this->tableSchema = $this->ormSchema->declareTable(
            $this->getDatabase(),
            $this->getTable()
        );

        //Casting table columns, indexes, foreign keys and etc
        $this->castTableSchema();
    }

    /**
     * Checks if class is abstract.
     *
     * @return bool
     */
    public function isAbstract()
    {
        return $this->reflection->isAbstract();
    }

    /**
     * Entity namespace. Both start and end namespace separators will be removed, to add start
     * separator (absolute) namespace use method parameter "absolute".
     *
     * @param bool $absolute \\ will be prepended to namespace if true, disabled by default.
     * @return string
     */
    public function getNamespace($absolute = false)
    {
        return ($absolute ? '\\' : '') . trim($this->reflection->getNamespaceName(), '\\');
    }

    /**
     * Entity full class name.
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    public function getReflection()
    {
        return $this->reflection;
    }

    /**
     * Entity class name without included namespace.
     *
     * @return string
     */
    public function getShortName()
    {
        return $this->reflection->getShortName();
    }

    /**
     * Get name should be used to represent entity relationship in foreign classes (default behaviour).
     *
     * Example:
     * Models\Post => HAS_ONE => post_id
     *
     * @return string
     */
    public function getRoleName()
    {
        return lcfirst($this->getShortName());
    }

    /**
     * True if entity allowed schema modifications.
     *
     * @return bool
     */
    public function isActiveSchema()
    {
        return $this->reflection->getConstant('ACTIVE_SCHEMA');
    }

    /**
     * Get associated table schema. Result can be empty if models is abstract or schema is empty.
     *
     * @return AbstractTableSchema|null
     */
    public function getTableSchema()
    {
        return $this->tableSchema;
    }

    /**
     * Reading default model property value, will read "protected" and "private" properties.
     *
     * @param string $property Property name.
     * @param bool   $merge    If true value will be merged with all parent declarations.
     * @return mixed
     */
    protected function property($property, $merge = false)
    {
        if (isset($this->propertiesCache[$property]))
        {
            return $this->propertiesCache[$property];
        }

        $defaults = $this->reflection->getDefaultProperties();
        if (isset($defaults[$property]))
        {
            $value = $defaults[$property];
        }
        else
        {
            return null;
        }

        if ($merge && ($this->reflection->getParentClass()->getName() != SchemaBuilder::ENTITY))
        {
            $parentClass = $this->reflection->getParentClass()->getName();
            if (is_array($value))
            {
                $value = array_merge(
                    $this->ormSchema->getEntity($parentClass)->property($property, true),
                    $value
                );
            }
        }

        return $this->propertiesCache[$property] = call_user_func(
            array($this->getClass(), 'describeProperty'),
            $this,
            $property,
            $value
        );
    }

    /**
     * Get table name associated with entity model.
     *
     * @return mixed
     */
    public function getTable()
    {
        $table = $this->property('table');

        if (empty($table))
        {
            //We can guess table name
            $table = $this->reflection->getShortName();
            $table = Inflector::tableize($table);

            //Table names are plural by default
            return Inflector::pluralize($table);
        }

        return $table;
    }

    /**
     * Get database model data should be stored in.
     *
     * @return mixed
     */
    public function getDatabase()
    {
        return $this->property('database');
    }

    /**
     * Get entity declared schema (merged with parent model(s) values).
     *
     * @return array
     */
    public function getSchema()
    {
        return $this->property('schema', true);
    }

    /**
     * Get declared indexes. This is not the same set of indexes which can be presented in table
     * schema, use EntitySchema->getTableSchema()->getIndexes() method for it.
     *
     * @see getTableSchema()
     * @return array
     */
    public function getIndexes()
    {
        return $this->property('indexes', true);
    }

    /**
     * Entity declared default values. No typecast here as it will be resolved on TableSchema level.
     *
     * @return array
     */
    public function getDefaults()
    {
        return $this->property('defaults', true);
    }

    /**
     * Find all field mutators.
     *
     * @return mixed
     */
    public function getMutators()
    {
        $mutators = array(
            'getter'   => array(),
            'setter'   => array(),
            'accessor' => array()
        );

        foreach ($this->property('getters', true) as $field => $filter)
        {
            $mutators['getter'][$field] = $filter;
        }

        foreach ($this->property('setters', true) as $field => $filter)
        {
            $mutators['setter'][$field] = $filter;
        }

        foreach ($this->property('accessors', true) as $field => $filter)
        {
            $mutators['accessor'][$field] = $filter;
        }

        //Default values.
        foreach ($this->getSchema() as $field => $type)
        {
            if (is_array($type))
            {
                continue;
            }

            $resolved = array();
            if ($filter = $this->ormSchema->getMutators($type))
            {
                $resolved += $filter;
            }

            if (isset($resolved['accessor']))
            {
                //Ensuring type for accessor
                $resolved['accessor'] = array($resolved['accessor'], $type);
            }

            foreach ($resolved as $mutator => $filter)
            {
                if (!array_key_exists($field, $mutators[$mutator]))
                {
                    $mutators[$mutator][$field] = $filter;
                }
            }
        }

        return $mutators;
    }

    /**
     * Getting all secured fields.
     *
     * @return array
     */
    public function getSecured()
    {
        return $this->property('secured', true);
    }

    /**
     * Getting all mass assignable fields.
     *
     * @return array
     */
    public function getFillable()
    {
        return $this->property('fillable', true);
    }

    /**
     * Getting all hidden fields.
     *
     * @return array
     */
    public function getHidden()
    {
        return $this->property('hidden', true);
    }

    /**
     * Get all entity validation rules (merged with parent model(s) values).
     *
     * @return array
     */
    public function getValidates()
    {
        return $this->property('validates', true);
    }

    /**
     * Get error messages localization sources. This is required to correctly localize model errors
     * without overlaps.
     *
     * @return array
     */
    public function getMessages()
    {
        $validates = array();
        $reflection = $this->reflection;
        while ($reflection->getName() != SchemaBuilder::ENTITY)
        {
            //Validation messages
            if (!empty($reflection->getDefaultProperties()['validates']))
            {
                $validates[$reflection->getName()] = $reflection->getDefaultProperties()['validates'];
            }

            $reflection = $reflection->getParentClass();
        }

        $messages = array();
        foreach (array_reverse($validates) as $parent => $validates)
        {
            foreach ($validates as $field => $rules)
            {
                foreach ($rules as $rule)
                {
                    $message = '';
                    if (isset($rule['message']))
                    {
                        $message = $rule['message'];
                    }
                    elseif (isset($rule['error']))
                    {
                        $message = $rule['error'];
                    }
                    if (
                        substr($message, 0, 2) == Translator::I18N_PREFIX
                        && substr($message, -2) == Translator::I18N_POSTFIX
                    )
                    {
                        //Only I18N messages
                        if ($message && !isset($errorMessages[$message]))
                        {
                            $messages[$message] = $parent;
                        }
                    }
                }
            }
        }

        return $messages;
    }

    /**
     * All methods declared in entity. Method will include information about parameters, return
     * type, static declaration and access level.
     *
     * @return MethodSchema[]
     */
    public function getMethods()
    {
        $methods = array();

        foreach ($this->reflection->getMethods() as $method)
        {
            if ($method->getDeclaringClass() != $this->reflection)
            {
                continue;
            }

            $methods[] = MethodSchema::make(array('reflection' => $method));
        }

        return $methods;
    }

    /**
     * Get document get filters (merged with parent model(s) values).
     *
     * @return array
     */
    public function getGetters()
    {
        return $this->getMutators()['getter'];
    }

    /**
     * Get document set filters (merged with parent model(s) values).
     *
     * @return array
     */
    public function getSetters()
    {
        return $this->getMutators()['setter'];
    }

    /**
     * Get document field accessors, this method will automatically create accessors for compositions.
     *
     * @return array
     */
    public function getAccessors()
    {
        return $this->getMutators()['accessor'];
    }

    /**
     * Name of first primary key (usually sequence).
     *
     * @return string
     */
    public function getPrimaryKey()
    {
        return array_slice($this->tableSchema->getPrimaryKeys(), 0, 1)[0];
    }

    /**
     * Fill table schema with declared columns, their default values and etc.
     */
    protected function castTableSchema()
    {
        $defaults = $this->getDefaults();
        foreach ($this->property('schema', true) as $name => $definition)
        {
            //Column definition
            if (is_string($definition))
            {
                //Filling column values
                $defaults[$name] = $this->castColumnSchema(
                    $this->tableSchema->column($name),
                    $definition,
                    isset($defaults[$name]) ? $defaults[$name] : null
                );
            }
        }

        //We can cast declared indexes there, however some relationships may cast more indexes
        foreach ($this->getIndexes() as $definition)
        {
            $this->castIndexSchema($definition);
        }
    }

    /**
     * Cast column schema based on provided column definition and default value. Spiral will force
     * default values (internally) for every NOT NULL column except primary keys.
     *
     * Column definition examples (by default all columns has flag NOT NULL):
     * id           => primary
     * name         => string       [default 255 symbols]
     * email        => string(255), nullable
     * status       => enum(active, pending, disabled)
     * balance      => decimal(10, 2)
     * message      => text, null[able]
     * time_expired => timestamp
     *
     * @param AbstractColumnSchema $column
     * @param string               $definition
     * @param mixed                $default Declared default value or null.
     * @return mixed
     * @throws ORMException
     */
    protected function castColumnSchema(AbstractColumnSchema $column, $definition, $default = null)
    {
        if (!is_null($default))
        {
            $column->defaultValue($default);
        }

        $validType = preg_match(
            '/(?P<type>[a-z]+)(?: *\((?P<options>[^\)]+)\))?(?: *, *(?P<nullable>null(?:able)?))?/i',
            $definition,
            $matches
        );

        //Parsing definition
        if (!$validType)
        {
            throw new ORMException(
                "Unable to parse definition of  column {$this->getClass()}.'{$column->getName()}'."
            );
        }

        if (!empty($matches['nullable']))
        {
            //No need to force NOT NULL as this is default column state
            $column->nullable(true);
        }

        $type = $matches['type'];

        $options = array();
        if (!empty($matches['options']))
        {
            $options = array_map('trim', explode(',', $matches['options']));
        }

        //DBAL will handle the rest of declaration
        call_user_func_array(array($column, $type), $options);

        $default = $column->getDefaultValue();
        if ($default instanceof SqlFragmentInterface)
        {
            //We have to rebuild default type in scalar form
            $default = null;
        }

        if (empty($default) && in_array($column->getName(), $this->tableSchema->getPrimaryKeys()))
        {
            return null;
        }

        //We have to cast default value to prevent errors
        if (empty($default) && !$column->isNullable())
        {
            $default = $this->castDefaultValue($column);
        }

        return $default;
    }

    /**
     * Cast default value based on column type.
     *
     * @param AbstractColumnSchema $column
     * @return bool|float|int|mixed|string
     */
    protected function castDefaultValue(AbstractColumnSchema $column)
    {
        //As no default value provided and column can not be null we can cast value by ourselves
        if ($column->abstractType() == 'timestamp' || $column->abstractType() == 'datetime')
        {
            $driver = $this->tableSchema->getDriver();

            return preg_replace('/[a-z]/i', '0', $driver::DATETIME);
        }
        else
        {
            switch ($column->phpType())
            {
                case 'int':
                    return 0;
                    break;
                case 'float':
                    return 0.0;
                    break;
                case 'bool':
                    return false;
                    break;
            }
        }

        return '';
    }

    /**
     * Create index in associated table based on index definition provided in model or model parent.
     * Attention, this method does not support primary indexes (for now). Additionally, some
     * relationships will create indexes automatically while defining foreign key.
     *
     * Examples:
     * protected $indexes = array(
     *      [self::UNIQUE, 'email'],
     *      [self::INDEX, 'status', 'balance'],
     *      [self::INDEX, 'public_id']
     * );
     *
     * @param array $definition
     * @throws ORMException
     */
    protected function castIndexSchema(array $definition)
    {
        $type = null;
        $columns = array();

        foreach ($definition as $chunk)
        {
            if ($chunk == Entity::INDEX || $chunk == Entity::UNIQUE)
            {
                $type = $chunk;
                continue;
            }

            if (!$this->tableSchema->hasColumn($chunk))
            {
                throw new ORMException("Model {$this->getClass()} has index with undefined column.");
            }

            $columns[] = $chunk;
        }

        if (empty($type))
        {
            throw new ORMException("Model {$this->getClass()} has index with unspecified type.");
        }

        //Defining index
        $this->tableSchema->index($columns)->unique($type == Entity::UNIQUE);
    }

    /**
     * Casting entity relationships.
     */
    public function castRelations()
    {
        foreach ($this->property('schema', true) as $name => $definition)
        {
            if (is_string($definition))
            {
                //Column definition
                continue;
            }

            $this->addRelation($name, $definition);
        }
    }

    /**
     * Get all declared entity relations.
     *
     * @return RelationSchema[]
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * Add relation to EntitySchema.
     *
     * @param string $name
     * @param array  $definition
     */
    public function addRelation($name, array $definition)
    {
        if (isset($this->relations[$name]))
        {
            self::logger()->warning(
                "Unable to create relation '{class}'.'{name}', connection already exists.",
                array(
                    'name'  => $name,
                    'class' => $this->getClass()
                ));

            return;
        }

        $relationship = $this->ormSchema->getRelationSchema($this, $name, $definition);

        //Initiating required columns, foreign keys and indexes
        $relationship->buildSchema($this);
        $this->relations[$name] = $relationship;
    }

    /**
     * Return entity class name.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getClass();
    }
}