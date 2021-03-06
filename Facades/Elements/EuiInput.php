<?php
namespace exface\JEasyUIFacade\Facades\Elements;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\Input;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryLiveReferenceTrait;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryDisableConditionTrait;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryInputValidationTrait;

/**
 *
 * @method Input getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
class EuiInput extends EuiValue
{
    use JqueryLiveReferenceTrait;
    use JqueryDisableConditionTrait;
    use JqueryInputValidationTrait {
        buildJsValidator as buildJsValidatorViaTrait;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiText::init()
     */
    protected function init()
    {
        parent::init();
        $this->setElementType('textbox');
        // If the input's value is bound to another element via an expression, we need to make sure, that other element will
        // change the input's value every time it changes itself. This needs to be done on init() to make sure, the other element
        // has not generated it's JS code yet!
        $this->registerLiveReferenceAtLinkedElement();
        
        // Register an onChange-Script on the element linked by a disable condition.
        $this->registerDisableConditionAtLinkedElement();
        
        if ($requiredIf = $this->getWidget()->getRequiredIf()) {
            $this->registerConditionalPropertyUpdaterOnLinkedElements($requiredIf, $this->buildJsRequiredSetter(true), $this->buildJsRequiredSetter(false));
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiText::buildHtml()
     */
    public function buildHtml()
    {
        /* @var $widget \exface\Core\Widgets\Input */
        $widget = $this->getWidget();
        
        $output = '	<input style="height: 100%; width: 100%;"
						name="' . $widget->getAttributeAlias() . '" 
						value="' . $this->escapeString($widget->getValueWithDefaults(), false, true) . '" 
						id="' . $this->getId() . '"  
						' . ($widget->isRequired() ? 'required="true" ' : '') . '
						' . ($widget->isDisabled() ? 'disabled="disabled" ' : '') . '
						/>
					';
        return $this->buildHtmlLabelWrapper($output);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiText::buildJs()
     */
    public function buildJs()
    {
        $output = '';
        $output .= "
				$('#" . $this->getId() . "')." . $this->getElementType() . "(" . ($this->buildJsDataOptions() ? '{' . $this->buildJsDataOptions() . '}' : '') . ");//.textbox('addClearBtn', 'icon-clear');
				";
        $output .= $this->buildJsEventScripts();
        return $output;
    }
    
    /**
     * Returns JS scripts for event handling like live references, onChange-handlers,
     * disable conditions, etc.
     * 
     * @return string
     */
    protected function buildJsEventScripts()
    {
        if ($this->getWidget()->isHidden() === true) {
            $hideInitiallyIfNeeded = $this->buildJsHideWidget();
        }
        
        if ($requiredIf = $this->getWidget()->getRequiredIf()) {
            $requiredIfInit = $this->buildJsConditionalPropertyInitializer($requiredIf, $this->buildJsRequiredSetter(true), $this->buildJsRequiredSetter(false));
        } else {
            $requiredIfInit = '';
        }
        
        
        return <<<JS

    // Event scripts for {$this->getId()}
    try {
        {$this->buildJsLiveReference()}
    } catch (e) {
        console.warn('Failed to update live reference: ' + e);
    }
    $(function() { 
        {$this->buildJsOnChangeHandler()}
        {$this->buildJsDisableConditionInitializer()}
        {$requiredIfInit}
        {$hideInitiallyIfNeeded}
    });

JS;
    }
    
    protected function buildJsRequiredSetter(bool $required) : string
    {
        return "$('#{$this->getId()}').{$this->getElementType()}('require'," . ($required ? 'true' : 'false') . ");";
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiAbstractElement::buildJsInitOptions()
     */
    public function buildJsInitOptions()
    {
        return $this->buildJsDataOptions();
    }

    /**
     * 
     * @return string
     */
    protected function buildJsDataOptions()
    {
        $options = '';
        
        if ($this->getOnChangeScript()) {
            $options .= "\n" . 'onChange: function(newValue, oldValue) {$(this).trigger("change");}';
        }
        
        return $options;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsValueSetterMethod()
     */
    public function buildJsValueSetterMethod($value)
    {
        return $this->getElementType() . '("setValue", ' . $value . ')';
    }

    /**
     * 
     * @return string
     */
    protected function buildJsOnChangeHandler()
    {
        if ($this->getOnChangeScript()) {
            return "$('#" . $this->getId() . "').change(function(event){" . $this->getOnChangeScript() . "});";
        } else {
            return '';
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsDataGetter($action, $custom_body_js)
     */
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        if ($this->getWidget()->isDisplayOnly()) {
            return '{}';
        } else {
            return parent::buildJsDataGetter($action);
        }
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsValidator()
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryInputValidationTrait::buildJsValidator()
     */
    public function buildJsValidator()
    {
        if ($this->isValidationRequired() === true) {
            return "$('#{$this->getId()}').{$this->getElementType()}('isValid')";
        }
        
        return $this->buildJsValidatorViaTrait();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsEnabler()
     */
    public function buildJsEnabler()
    {
        return '$("#' . $this->getId() . '").' . $this->getElementType() . '("enable").' . $this->getElementType() . '("validate")';
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsDisabler()
     */
    public function buildJsDisabler()
    {
        return '$("#' . $this->getId() . '").' . $this->getElementType() . '("disable")';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiText::buildCssHeightDefaultValue()
     */
    protected function buildCssHeightDefaultValue()
    {
        return ($this->getHeightRelativeUnit() * 1) . 'px';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiValue::buildCssElementClass()
     */
    public function buildCssElementClass()
    {
        return parent::buildCssElementClass() . ' exf-input';
    }
}
?>