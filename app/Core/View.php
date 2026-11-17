<?php
namespace App\Core;

/**
 * 极简模板渲染器
 * 支持在模板中直接使用 $this->var 或局部变量，输出自动转义可调用 Util::e。
 */
class View
{
    private $file;
    private $vars = array();

    public function __construct($file, $vars = array())
    {
        $this->file = $file;
        $this->vars = $vars;
    }

    /**
     * 渲染模板并返回字符串
     * @return string
     */
    public function render()
    {
        extract($this->vars, EXTR_SKIP);
        ob_start();
        include $this->file;
        return ob_get_clean();
    }

    /**
     * 渲染后台页面（带布局）
     * @param string $view 视图相对路径，如 card/index
     * @param array $vars 变量
     * @param string $title 页面标题
     */
    public static function admin($view, $vars, $title = '')
    {
        $vars['title'] = $title;
        $vars['contentHtml'] = self::make('app/views/' . $view . '.php', $vars)->render();
        echo self::make('app/views/layout.php', $vars)->render();
    }

    /**
     * 直接输出视图
     * @param string $relativePath 相对项目根的路径
     * @param array $vars 变量
     */
    public static function make($relativePath, $vars = array())
    {
        return new self(Config::root() . '/' . $relativePath, $vars);
    }

    /**
     * 输出后台页面
     */
    public static function page($view, $vars, $title = '')
    {
        self::admin($view, $vars, $title);
    }
}
