<?php

namespace MGN\Bundle\BootstrapCrudBundle\Service;

// SF2 use
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Translation\Translator;
// Bundle use
use MGN\Bundle\BootstrapCrudBundle\Form\EmptyType;

/**
 * An abstract class representing a controler in the application
 */
class GenericCrudServiceController
{
    /* Bundle variable */
    private $entityNamespace = null;
    private $formNamespace   = null;
    private $bundleName      = null;

    /* crud route variable */
    private $crudParameters  = array();

    /**
     * Build the service
     *
     * @param Symfony\Bridge\Doctrine\RegistryInterface $doctrine doctrine service
     * @param Symfony\Component\Templating\EngineInterface $templating templating service
     * @param Symfony\Component\Form\FormFactory $formFactory form factory service
     * @param Symfony\Component\Translation\Translator $translator translator service
     * @param $entityNamespace the namespace of entities
     * @param $formNamespace   the namespace of form
     * @param $bundleName      the name of the bundle
     */
    function __construct(RegistryInterface $doctrine, EngineInterface $templating, FormFactory $formFactory, Translator $translator, $entityNamespace, $formNamespace, $bundleName)
    {
	$this->doctrine    = $doctrine;
	$this->templating  = $templating;
        $this->translator  = $translator;
	$this->formFactory = $formFactory;

        $this->entityNamespace = $entityNamespace;
        $this->formNamespace   = $formNamespace;
        $this->bundleName      = $bundleName;
    }

    /**
     * Initialise route for ajax display
     *
     * @param $crudName  uniq identifier for the crud
     * @param $entityName name of the entity to crud 
     * @param $attributes array of attribute to display in the list
     * @param $listRoute name of route to list objects
     * @param $editRoute name of route to edit a object
     * @param $dropRoute name of route to drop a object
     */
    public function addEntityCrud($crudName, $entityName, $attributes, $listRoute, $editRoute, $newRoute, $dropRoute, $resultByPage = 10)
    {
        if(!class_exists($this->entityNamespace . "\\" . $entityName))
        {
            throw new \Exception("invalid className:" . $this->entityNamespace . "\\" . $entityName);
        }

	$this->crudParameters[$crudName] = array(
		'entityName'   => $entityName,
		'attributes'   => $attributes,
		'listRoute'    => $listRoute,
		'editRoute'    => $editRoute,
                'newRoute'     => $newRoute,
		'dropRoute'    => $dropRoute,
                'resultByPage' => $resultByPage,
	);
    }

    /**
     * Return the translator Service
     *
     * @return Symfony\Component\Translation\Translator
     */ 
    protected function getTranslator()
    {
        return $this->translator;
    }

    /**
     * Return Doctrine service
     * 
     * @return Symfony\Bridge\Doctrine\RegistryInterface
     */
    protected function getDoctrine()
    {
	return $this->doctrine;
    }

    /**
     * Return templating service
     * 
     * @return Symfony\Component\Templating\EngineInterface
     */
    protected function getTemplating()
    {
        return $this->templating;
    }

    /**
     * Return a form object form object and type
     * 
     * @param $type form type to use
     * @param $object the object to edit
     * @return Symfony\Component\Form\Form
     */
    protected function createForm($type, $object)
    {
        return $this->formFactory->create($type, $object);
    }

    /**
     * Return repository instace for a class
     *
     * @param $className name of class for repository
     * @return Doctrine\ORM\EntityRepository
     */
    protected function getRepository($className)
    {
        return $this->getDoctrine()->getRepository($this->bundleName . ':' . $className);
    }


    /**
     * list Object controller
     *
     * @param Request $request
     * @param $crudName identifier of crud to use
     * @param $type type of the request possible value is "ajax", "json"
     * @param $page number of page to display
     * @throws Symfony\Component\HttpKernel\Exception\NotFoundHttpException if object not exist
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function listObjectsAction(Request $request, $crudName, $page = 1, $direction, $attribute, $search, $type = "ajax")
    {
	/* Check if Route is initialise */
        if(!isset($this->crudParameters[$crudName])) {
		throw new NotFoundHttpException();
	}

	$crudParameters = $this->crudParameters[$crudName];
	$className      = $crudParameters['entityName'];
	$attributes     = $crudParameters['attributes'];

        /* Build OrderBy attribute */
	if($attribute != "")
        {
	    if($direction == "up")
            {
                $orderBy = array($attribute => 'asc');
            } else {
                $orderBy = array($attribute => 'desc');
            }
        } else {
            $orderBy = null;
	}


        $labelAttributes = array();
        $cmf = $this->getDoctrine()->getEntityManager()->getMetadataFactory();
        $class = $cmf->getMetadataFor($this->bundleName . ':' . $className);
        $criteria = array();
        foreach($attributes as $attributeName)
        {
            $labelAttributes[$attributeName] = $this->getTranslator()->trans($attributeName, array(), strtolower($className));
            $sortableAttributes[$attributeName] = $class->hasField($attributeName);
            if($sortableAttributes[$attributeName] && $search != "") 
            {
                $criteria[$attributeName] = $search;
            }
        }

        $nbObject       = count($this->getRepository($className)->findBy($criteria, null));
        $maxPage        = intval($nbObject / $crudParameters['resultByPage']) + 1;

        $objects = $this->getRepository($className)->findBy($criteria, $orderBy, $crudParameters['resultByPage'], ($page - 1) * $crudParameters['resultByPage']);
        $deleteForm = $this->createForm(new EmptyType(), null);

        // Pass argument to the view
        $engine = $this->getTemplating();
        $content = $engine->render('MGNBootstrapCrudBundle:Generic:list.html.ajax.twig', array(
                'page'            => $page,
		'maxPage'         => $maxPage,
                'objects'         => $objects,
                'attributes'      => $attributes,
                'className'       => strtolower($className),
                'listRoute'       => $crudParameters['listRoute'],
                'editRoute'       => $crudParameters['editRoute'],
                'newRoute'        => $crudParameters['newRoute'],
                'dropRoute'       => $crudParameters['dropRoute'],
                'labelAttributes' => $labelAttributes,
                'orderDirection'  => $direction,
                'orderAttribute'  => $attribute,
                'searchFilter'    => $search,
                'sortableAttributes' => $sortableAttributes,
                'deleteForm'      => $deleteForm->createView(),
        ));

        return $response = new Response($content);
    }

    /**
     * drop Object controller part
     *
     * @param Request $request
     * @param $className Type of object to drop
     * @param $object the object to drop
     * @param $type type of the request possible value is "ajax", "json"
     * @throws Symfony\Component\HttpKernel\Exception\NotFoundHttpException if object not exist
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function dropObjectAction(Request $request, $crudName, $object, $type = "ajax")
    {
        /* Check if Route is initialise */
        if(!isset($this->crudParameters[$crudName])) {
                throw new NotFoundHttpException();
        }
        $crudParameters = $this->crudParameters[$crudName];
        $className = $crudParameters['entityName'];

        $em = $this->getDoctrine()->getEntityManager();
	$form = $this->createForm(new EmptyType(), null);

        if ($request->getMethod() == 'POST') {
                $form->bindRequest($request);
                if ($form->isValid()) {
                    $objectId = $object;
                    $object = $this->getRepository($className)->find($objectId);
                    if($object == null) 
                    {
                        throw new NotFoundHttpException();
                    }
                    $em->remove($object);
                    $em->flush();

                    // Pass argument to the view
                    $engine = $this->getTemplating();
                    $content = $engine->render('MGNBootstrapCrudBundle:Generic:drop.html.ajax.twig');

                    return $response = new Response($content);
               }
        }

        throw new NotFoundHttpException();
    }

    /**
     * edit Object controller
     *
     * @param $request the request object
     * @param $className the class to get Form
     * @param $object the object to edit | null if it's a new object
     * @param $type the type of the request
     * @throws Symfony\Component\HttpKernel\Exception\NotFoundHttpException if object not exist
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function editObjectAction(Request $request, $crudName, $object = null, $type = "ajax")
    {
        /* Check if Route is initialise */
        if(!isset($this->crudParameters[$crudName])) {
                throw new NotFoundHttpException();
        }
        $crudParameters = $this->crudParameters[$crudName];
        $className = $crudParameters['entityName'];

        $em = $this->getDoctrine()->getEntityManager();

	// Construct the form object
	$objectId = null;
	$classPath = $this->entityNamespace . "\\" . $className;
	$formClassPath = $this->formNamespace . "\\" . $className . "Type";
	if($object == null)
        {
            $object = new $classPath();
        } else {
            $objectId = $object;
            $object = $this->getRepository($className)->find($objectId);
	}
        $form = $this->createForm(new $formClassPath(), $object);
        $persisted = false;

        if ($request->getMethod() == 'POST') {
                $form->bindRequest($request);

                if ($form->isValid()) {
                    // saving the object to the database
                    $em->persist($object);
                    $em->flush();
                    $persisted = true;
                }
        }

        // Pass argument to the view
	$engine = $this->getTemplating();
        $content = $engine->render('MGNBootstrapCrudBundle:Generic:edit.html.ajax.twig', array(
                'form'      => $form->createView(),
                'persisted' => $persisted,
                'objectId'  => $objectId,
		'className' => strtolower($className),
        ));

        return $response = new Response($content);
    }

}
