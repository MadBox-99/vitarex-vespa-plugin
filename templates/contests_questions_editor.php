<?php 
    global $wpdb;
    $site_title = 'Beszámoló kérdések';
?>
<style type="text/css">
.ui-icon {
  -ms-transform: scale(1.3); /* IE 9 */
  -webkit-transform: scale(1.3); /* Chrome, Safari, Opera */
  transform: scale(1.3);
}
.center {
  margin: 0;
  position: absolute;
  top: 50%;
  left: 50%;
}
.borderlist 
{
    border: 1px solid black;
}
</style>
<script src="https://code.jquery.com/jquery-3.6.0.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
<script>
let questionList = []

function renderQuestionList(){
    console.log('renderQuestionList')
    $('#sortable').empty()
    questionList.forEach(question => {
        let listItem = 
        `
        <div class="row vespa-box" style="margin: 10px 10px 10px 10px;">
        <li id="li-${questionList.indexOf(question)}">
                <div class="col-md-1"><span class="ui-icon ui-icon-arrowthick-2-n-s center"></span></div>
                <div class="col-md-10">
                    <div class="form-group">
                        <label>${questionList.indexOf(question) + 1}. kérdés</label>
                        <input type="text" class="form-control" name="question" id="question-${questionList.indexOf(question)}" autocomplete="off" value="${question.question}" onchange="bindQuestionChange(${questionList.indexOf(question)},this.value)">
                    </div>
                    <div class="form-group">
                        <label>Válaszok (a különböző válaszokat új sorba írja)</label>
                        <textarea rows="5" class="form-control" name="answers" id="answers-${questionList.indexOf(question)}" autocomplete="off" onchange="bindAnswerChange(${questionList.indexOf(question)},this.value)">${question.answers}</textarea>
                    </div>
                </div>
                <div class="col-md-1"><span class="ui-icon ui-icon-closethick center" onclick=removeQuestion(${questionList.indexOf(question)})></span></div>
        </li>
        </div>
        `
        $("#sortable").append(listItem);     
    })
}

function bindQuestionChange(index, change){
    questionList[index].question = change
}

function bindAnswerChange(index, change){
    questionList[index].answers = change
}

function addNewQuestion(){
    console.log('addNewQuestion')
    questionList.push({
        question: '',
        answers: '',
        question_id: 0,
        ordernum: 0
    })
    console.log(questionList.length)
    renderQuestionList()
}

function removeQuestion(index){
    console.log('removeQuestion', index)
    questionList = questionList.filter(question => questionList.indexOf(question) !== index)
    renderQuestionList()
}

function presaveQuestions(){
    console.log('presaveQuestions')
    if(questionList.length == 0)
        return
    let isError = false;
    questionList.forEach(question => {
        question.ordernum = questionList.indexOf(question)
        if(question.question == '')
        {
            jQuery(`#question-${question.ordernum}`).addClass('is-invalid'); 
            isError = true;
        }
        else
            jQuery(`#question-${question.ordernum}`).removeClass('is-invalid');
        if(question.answers == '')
        {
            jQuery(`#answers-${question.ordernum}`).addClass('is-invalid');
            isError = true;
        } 
        else
            jQuery(`#answers-${question.ordernum}`).removeClass('is-invalid');
    })

    if(!isError){
        saveQuestions(questionList)
    }
}

function saveQuestions(){
    const qData = [...questionList]
    qData.forEach(question => {
        question.answers = question.answers.replaceAll('\n', ';')
    })
    jQuery.ajax({
        type: 'POST',
        dataType: 'json',
        url: vitarex_vespa_ajaxurl,
        data: { 
            'action': 'vespa_ajax_list_contest_questions_save',
            'data': qData
        },
        success: function( response ){
            if(response.data.modal) {
                document.body.insertAdjacentHTML('beforeend', response.data.modal );
                jQuery('#' + response.data.modalId ).modal('show');
            }
        }
    });     
}

Array.prototype.move = function (from, to) {
  this.splice(to, 0, this.splice(from, 1)[0]);
};

let oldIndex = 0
$( function() {
$( "#sortable" ).sortable(
    {
        update: function(event, ui) { 
            console.log('update: '+ui.item.index())
            questionList.move(oldIndex, ui.item.index())
            renderQuestionList()
        },
        start: function(event, ui) { 
            console.log('start: ' + ui.item.index())
            oldIndex = ui.item.index()
        }
    }
);
} );
</script>

<div class="wrap">
    <div class="row">
        <div class="col-md-12">
            <h1 class="site-title"><?php echo $site_title; ?></h1>
        </div>
    </div>

      <!-- =========================================== -->
    <div class="vespa-box" id="kiserok">
    <div class="row">
        <div class="col-md-6">
            <h3 style="margin-top:0">Kérdések</h3>
        </div>

        <div class="col-md-6">
            <?php if( current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles ) ): ?>
            <button class="btn btn-primary pull-right" onclick="presaveQuestions();">Mentés</button>
            <button class="btn btn-default pull-right" style="margin-left:5px;margin-right:5px;" onclick="addNewQuestion();">Új kérdés felvétele</button>
            <?php endif; ?>
        </div>

        <div class="col-md-12"> 
        <ul id="sortable"></ul>
            <script>
                jQuery(document).ready(function($){
                    console.log('ready')
                    jQuery.ajax({
                    type: 'POST',
                    dataType: 'json',
                    url: vitarex_vespa_ajaxurl,
                    data: { 
                        'action': 'vespa_ajax_list_contest_questions_get'
                    },
                    success: function( resp ){
                        console.log('data', resp)
                        resp.data?.forEach(question => {
                            question.answers = question.answers.replaceAll(';', '\n')
                            questionList.push(question)
                        })
                        renderQuestionList()
                    }
                });     
                });
            </script>
        </div>
    </div>
    </div>

    <!-- =========================================== -->
</div>
