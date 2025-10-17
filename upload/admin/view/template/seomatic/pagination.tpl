
    <div class="row">
        <div class="col-sm-6 text-left">
        	<ul class="pagination">

			    <?php if($this->prev && $this->page > 0){ ?>

			        <li><a href="<?php echo $this->prev; ?>">&lt;</a></li>
			    
			    <?php } ?>

			    <?php if($this->next && ($this->limit * ($this->page+1) < $this->total )){ ?>
			    
			        <li><a href="<?php echo $this->next; ?>">&gt;</a></li>
			    
			    <?php } ?>

        	</ul>
        </div>
        <div class="col-sm-6 text-right">
        	Showing <?php echo ( ( $this->page ) * $this->limit ) + 1; ?> to <?php echo ( $this->page+1 ) * $this->limit; ?> of <?php echo $this->total; ?> (<?php echo ceil($this->total / $this->limit); ?> Pages)
       	</div>
    </div>