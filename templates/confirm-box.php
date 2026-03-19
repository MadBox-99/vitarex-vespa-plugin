<!-- Modal -->
<div class="modal fade" id="confirmBox{=MODALID=}" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">Megerősítés</h4>
        </div>

        <input type="hidden" id="action" name="action" value="{=ACTION=}" autocomplete="off">
        <input type="hidden" id="record_id" name="record_id" value="" autocomplete="off">

      
        <div class="modal-body" style="text-align: center;">
            <p style="font-size: 1.3em;">{=TEXT=}</p>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Mégsem</button>
            <button type="button" class="btn btn-danger" onclick="confirmAction( this );">Törlés</button>            
        </div>
    </div>
  </div>
</div>