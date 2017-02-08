import React,{ Component } from 'react';

export default class Options extends Component {
  constructor(props) {
    super(props);
    this.state = {
      datas: this.props.datas,
    }
    this.editor = null;
    this.editorBody = null;
    this.editorHtml = null;
  }
 
  componentDidMount() {
    console.log('componentDidMount');
    this.initCkeditor();
  }

  deleteOption(event) {
    this.editorHtml = null;
    this.props.deleteOption(event.currentTarget.attributes["data-option-id"].value);
  }

  onChangeChecked(event) {
    this.props.changeOptionChecked(event.currentTarget.attributes["data-option-id"].value,this.props.datas.checked);
  }

  initCkeditor(dataSourceUi) {
    if(!this.editor) {
      this.editor = CKEDITOR.replace(this.props.datas.optionId, {
        toolbar: 'Minimal',
        filebrowserImageUploadUrl:this.props.imageUploadUrl,
        height: 120
      });
      let self = this;
      this.editor.on("instanceReady", function () {  
        self.editorBody = $('#' + [self.props.datas.optionId]).parent().find('iframe').contents().find('body');
        //setData两个问题：1、引发事件失效 2、死循环触发；
      }); 
      this.editor.on('change',function(){
        setTimeout(function(){
          self.updateInputValue(self.editor.getData());
        },100)
      });
    }else {
      this.editor.setData(datas.inputValue);
    }
  }

  updateInputValue(inputValue) {
    this.editorHtml = inputValue;
    this.props.updateInputValue(this.props.datas.optionId,inputValue);
  }

  render() {
    let showDanger = this.props.isValidator && this.props.datas.inputValue.length <= 0;
    let type = 'checkbox';
    if(this.props.isRadio) {
      type= 'radio';
    }
    if(this.editorBody && this.editorHtml != this.props.datas.inputValue) {
      this.editorBody.html(this.props.datas.inputValue);
    }
    return (
      <div className="form-group">
        <div className="col-sm-2 control-label">
          <label className="choice-label control-label-required">{this.props.datas.optionLabel}</label>
        </div>
        <div className="col-sm-8 controls">
          <textarea className="form-control datas-input col-md-8" id={this.props.datas.optionId}  defaultValue={this.props.datas.inputValue} name='choices[]' value={this.props.datas.inputValue} data-image-upload-url={this.props.imageUploadUrl} data-image-download-url={this.props.imageDownloadUrl}></textarea>
          <div className="mtm">
            <label>
              <input type={type} name='answer[]' data-option-id={this.props.datas.optionId} value={this.props.index}  checked={this.props.datas.checked} className="answer-checkbox" onChange = {(event)=>this.onChangeChecked(event)}/>正确答案 
            </label>
          </div>
          <p className={showDanger ? 'color-danger' : 'hidden'}>请输入选项内容</p>
        </div>
        <div className="col-sm-2">
          <a className="btn btn-default btn-sm" data-option-id={ this.props.datas.optionId }  onClick={(event)=>this.deleteOption(event)} href="javascript:;"><i className="glyphicon glyphicon-trash"></i></a>
        </div>
      </div>
    )
  }
}