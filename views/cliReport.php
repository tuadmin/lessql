LessQL Runner Version <?php echo $report[ 'version' ] ?>


Log
---
<?php foreach ( $report[ 'log' ] as $item ): ?>
<?php echo $item[ 'id' ] ?>: <?php echo $item[ 'message' ] ?>

<?php endforeach ?>

History
-------
<?php foreach ( $report[ 'history' ] as $item ): ?>
<?php
	list( $u, $s ) = explode( ' ', $item[ 'executed' ] );
	echo date( 'Y-m-d H:i:s', $s );
?> - <?php echo $item[ 'id' ] ?>

<?php endforeach ?>

<?php echo $report[ 'ok' ] ? 'OK' : 'FAILED' ?>

<?php echo '' // newline ?>
