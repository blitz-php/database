<@php

<?php if (! $anonymous): ?>
namespace {namespace};

<?php endif; ?>
use BlitzPHP\Database\Migration\Migration;
use BlitzPHP\Database\Migration\Builder;

<?php if ($anonymous): ?>
return new class extends Migration
<?php else: ?>
class {class} extends Migration
<?php endif; ?>
{
	/**
     * Exécute la migration.
     */
	public function up(): void
	{
<?php if ($action === 'create' && $session): ?>
		$this->create('<?= $table ?>', function(Builder $table) {
			$table->string('id', 128);
			$table->ipAddress();
			$table->timestamp('timestamp');
			$table->binary('data');
			$table->index('timestamp');
<?php if ($matchIP): ?>
			$table->primary(['id', 'ip_address']);
<?php else: ?>
			$table->primary('id');
<?php endif; ?>
		});
<?php elseif ($action === 'create' && ! empty($table)): ?>
		$this->create('<?= $table ?>', function(Builder $table) {
	    	$table->id();
	    	$table->timestamps();
		});
<?php elseif ($action === 'drop' && ! empty($table)): ?>
		$this->dropIfExists('<?= $table ?>');
<?php elseif ($action === 'alter' && ! empty($table)): ?>
		$this->alter('<?= $table ?>', function(Builder $table) {
	    	//
		});
<?php else: ?>
		//
<?php endif; ?>
    }

	/**
     * Annulle la migration.
     */
	public function down(): void
    {
<?php if ($action === 'create' && ! empty($table)): ?>
		$this->dropIfExists('<?= $table ?>');
<?php elseif ($action === 'drop' && ! empty($table)): ?>
		$this->create('<?= $table ?>', function(Builder $table) {
	    	$table->id();
	    	$table->timestamps();
		});
<?php elseif ($action === 'alter' && ! empty($table)): ?>
		$this->alter('<?= $table ?>', function(Builder $table) {
	    	//
		});
<?php else: ?>
		//
<?php endif; ?>
    }
}<?= $anonymous ? ';' : '' ?>
