Getting Started With DataGridBundle
===================================

Datagrid for Symfony2 highly inspired by Zfdatagrid and Magento Grid but not compatible.

**Compatibility**: Symfony 2.0+ and will follow stable releases

## Installation

### Step 1: Download DataGridBundle

Ultimately, the DataGridBundle files should be downloaded to the
`vendor/bundles/Sorien/DataGridBundle` directory.

This can be done in several ways, depending on your preference. The first
method is the standard Symfony2 method.

**Using the vendors script**

Add the following lines in your `deps` file:

```
[DataGridBundle]
    git=git://github.com/S0RIEN/DataGridBundle.git
    target=bundles/Sorien/DataGridBundle
```

Now, run the vendors script to download the bundle:

``` bash
$ php bin/vendors install
```

**Using submodules**

If you prefer instead to use git submodules, the run the following:

``` bash
$ git submodule add git://github.com/S0RIEN/DataGridBundle.git vendor/bundles/Sorien/DataGridBundle
$ git submodule update --init
```

### Step 2: Configure the Autoloader

Add the `FOS` namespace to your autoloader:

``` php
<?php
// app/autoload.php

$loader->registerNamespaces(array(
    // ...
    'Sorien' => __DIR__.'/../vendor/bundles',
));
```

### Step 3: Enable the bundle

Finally, enable the bundle in the kernel:

``` php
<?php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        // ...
		new Sorien\DataGridBundle\SorienDataGridBundle(),
    );
}
```

### Step 3: Import FOSUserBundle routing files

Now that you have activated and configured the bundle, all that is left to do is
import the FOSUserBundle routing files.

By importing the routing files you will have ready made pages for things such as
logging in, creating users, etc.

In YAML:

``` yaml
# app/config/routing.yml
my_controller_grid:
	pattern:  /grid
	defaults: { _controller: MyBundle:Default:myGrid }
```

Or if you prefer XML:

``` xml
<!-- app/config/routing.xml -->
<route id="my_controller_grid" pattern="/grid">
	<default key="_controller">MyBundle:Default:myGrid</default>
</route>
```

### Next Steps

Now that you have completed the basic installation and configuration of the
DataGridBundle, you are ready to learn about more advanced features and usages
of the bundle.

The following documents are available:

1. [Grid Configuration](https://github.com/Abhoryo/DataGridBundle/blob/master/Resources/doc/grid_configuration.md)
2. [Annotations](https://github.com/Abhoryo/DataGridBundle/blob/master/Resources/doc/annotations.md)
3. [Overriding Templates](https://github.com/Abhoryo/DataGridBundle/blob/master/Resources/doc/overriding_templates.md)

## Simple grid with ORM or ODM as source

```php
<?php
// MyProject\MyBundle\DefaultController.php
namespace Djette\UserBundle\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sorien\DataGridBundle\Grid\Source\Entity;
use Sorien\DataGridBundle\Grid\Source\Document;

class DefaultController extends Controller
{
	public function myGridAction()
	{
		// Creates simple grid based on your entity (ORM)
		$source = new Entity('MyProjectMyBundle:MyEntity');
		
		// or use Document source class for ODM
		$source = new Document('MyProjectMyBundle:MyDocument');
		
		$grid = $this->get('grid');

		// Mass actions, query and row manipulations are defined here
		
		$grid->setSource($source);
		
		// Columns, row actions are defined here

		if ($grid->isReadyForRedirect())
		{
			// Data are stored, do redirect
			return new RedirectResponse($this->generateUrl('my_controller_filter'));
		}
		else
		{
			// To obtain data for template you need to call prepare function
			return $this->render('MyProjectMyBundle::my_grid.html.twig', array('data' => $grid->prepare()));
		}
	}
}
?>
```

```html
<!-- MyProject\MyBundle\Resources\views\my_grid.html.twig -->
{{ grid(data) }}
```

Working preview
-----
<img src="http://vortex-portal.com/datagrid/grid2.png" alt="Screenshot" />
