Options
=======

Options class built in PHP with PDO

Ready for use but not finished for distribution, needs proper organization, tests, and some other things, I just needed some place where to put it.

	Option::set('framework', array(
		'author' => array(
			'name' => 'João Sardinha',
			'email' => 'johnsardine@gmail.com'
		),
	));
	
	Option::get('framework.author'); // João Sardinha
	
	Option::set('framework.author.name', 'Johnsardine');
	Option::get('framework.author'); // Johnsardine
	
	Option::get('framework.what', 'default'); // what nao existe, retorna "default"
	
	// Se a opção for multilingua
	
	// Quando a lingua actual pt
	Option::get('site_name'); // Teste PT
	
	// Quando a lingua actual en
	Option::get('site_name'); // Teste EN
	
	// Devolve automaticamente a da lingua actual
	
	// Ou de forma manual
	Option::get('site_name', null, 'en_US'); // Teste EN	