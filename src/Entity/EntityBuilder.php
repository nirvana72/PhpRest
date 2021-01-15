<?php
namespace PhpRest\Entity;

use PhpRest\Annotation\AnnotationReader;
use PhpRest\Annotation\AnnotationBlock;
use PhpRest\Annotation\AnnotationTag;
use PhpRest\Entity\Annotation\ClassHandler;
use PhpRest\Entity\Annotation\TableHandler;
use PhpRest\Entity\Annotation\PropertyHandler;
use PhpRest\Entity\Annotation\FieldHandler;
use PhpRest\Entity\Annotation\VarHandler;
use PhpRest\Entity\Annotation\RuleHandler;
use phpDocumentor\Reflection\DocBlock\Tags\Var_ as VarTag;

class EntityBuilder
{
    private $annotationnHandlers = [
        [ClassHandler::class,     'class'],
        [TableHandler::class,     "class.children[?name=='table']"],
        [PropertyHandler::class,  'properties'],
        [FieldHandler::class,     "properties.*.children[?name=='field'][]"],
        [VarHandler::class,       "properties.*.children[?name=='var'][]"],
        [RuleHandler::class,      "properties.*.children[?name=='rule'][]"],
    ];

    public function build($classPath) 
    {
        // TODO 缓存
        $entity = new Entity($classPath);
        $classRef = new \ReflectionClass($classPath) or \PhpRest\abort("load class $classPath failed");
        $annotationReader = $this->buildAnnotationReader($classRef);
        foreach ($this->annotationnHandlers as $handler) {
            list($class, $expression) = $handler;
            $annotations = \JmesPath\search($expression, $annotationReader);
            if ($annotations !== null) {
                if($expression === 'class') {
                    $annotations = [ $annotations ]; // class不会匹配成数组
                }
                foreach ($annotations as $annotation) {
                    $annotationHandler = new $class();
                    $annotationHandler($entity, $annotation);
                }
            }
        }
        return $entity;
    }

    /**
     * 解析entity文件
     * 
     * @param ReflectionClass $classRef entity反射类
     * @return AnnotationReader
     */
    private function buildAnnotationReader($classRef) {
        $reader = new AnnotationReader();
        $docComment = $classRef->getDocComment();
        if ($docComment === false) { 
            // entityClass 没写注解, 默认可以不写注解
            $reader->class = new AnnotationBlock();            
            $reader->class->summary = $classRef->getShortName();
        } else {
            $reader->class = $this->readAnnotationBlock($docComment);
        }
        $reader->class->position = 'class';
        
        // 遍历属性
        foreach ($classRef->getProperties() as $property) {
          
            // 过滤
            if ($property->isDefault() === false ||
                $property->isStatic()  === true || 
                $property->isPublic()  === false) { continue; }
            
            $docComment = $property->getDocComment();
            $block = $this->readAnnotationBlock($docComment);
            $block->name = $property->getName();
            $block->position = 'property';
            $reader->properties[$block->name] = $block;
        }

        return $reader;
    }

    /**
     * 解析注解块
     * 
     * @param string $docComment 注解内容
     * @return AnnotationBlock
     */
    private function readAnnotationBlock($docComment) 
    {
        $annBlock = new AnnotationBlock();
        if ($docComment !== false) {
            $factory = \phpDocumentor\Reflection\DocBlockFactory::createInstance();
            $docBlock = $factory->create($docComment);
            $annBlock->summary     = $docBlock->getSummary();
            $annBlock->description = $docBlock->getDescription()->render();
            $tags = $docBlock->getTags(); 
            foreach ($tags as $tag) {
                $annTag = new AnnotationTag();
                $annTag->parent      = $annBlock;
                $annTag->name        = $tag->getName();
                if ($tag instanceof VarTag) {
                    $type = (string)$tag->getType();
                    if ($type[0] === '\\') $type = substr($type, 1);
                    $annTag->description = $type;
                } else {
                    $annTag->description = $tag->getDescription()->render();
                }
                $annBlock->children[] = $annTag;
            }
        }
        return $annBlock;
    }
}