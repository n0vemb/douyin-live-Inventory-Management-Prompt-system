// label-elements.js
// 标签打印台 自定义打印元素配置 —— 适配本项目「入库批次标签」真实数据模型。
//
// 每个元素的 field 对应 purchase_logs.php / api/direct_print.php 喂给模板的数据键：
//   productName 商品名称 | commonName 常用名 | series 系列 | barcode 条码
//   price 售价 | conditionType SKU状态 | batchNo 批次号 | purchasedAt 入库日期
// tid 规则：labelModule.<简单格式type>，保存时据此把 hiprint 模板转回简单 elements 格式，
// 与 PHP GD / 浏览器 canvas / iframe 打印三条渲染链路完全兼容。
//
// 依赖：必须在加载 hiprint.bundle.js 之后再加载本文件。
// 暴露：window.LabelProvider / window.LABEL_PANEL_GROUPS
(function (global) {
  'use strict';

  function getHiprint() {
    if (global.hiprint) return global.hiprint;
    var pkg = global['vue-plugin-hiprint'];
    return pkg && pkg.hiprint;
  }

  var hiprint = getHiprint();

  // 设计器示例数据：优先取页面注入的真实商品数据（purchase_logs.php 打开编辑器时设置
  // window.__LABEL_TESTDATA），无则回退内置示例，保证设计器所见即所得
  function labelTestData(key, fallback) {
    var m = global.__LABEL_TESTDATA;
    var v = m && m[key];
    return (v != null && v !== '') ? v : fallback;
  }

  var LABEL_ELEMENTS = [
    new hiprint.PrintElementTypeGroup("商品信息", [
      {
        tid: "labelModule.name",
        title: "商品名称",
        type: "text",
        options: {
          field: "productName",
          testData: labelTestData("name", "淘淘圈轮毂 19寸 5x114.3 ET35"),
          height: 17,
          fontSize: 13,
          fontWeight: "700",
          verticalAlign: "top",
          hideTitle: true
        }
      },
      {
        tid: "labelModule.common",
        title: "常用名",
        type: "text",
        options: {
          field: "commonName",
          testData: labelTestData("common", "19寸改装款"),
          height: 12.5,
          fontSize: 10,
          color: "#555555",
          verticalAlign: "top",
          hideTitle: true
        }
      },
      {
        tid: "labelModule.series",
        title: "系列",
        type: "text",
        options: {
          field: "series",
          testData: labelTestData("series", "GT 系列"),
          height: 12.5,
          fontSize: 10,
          color: "#555555",
          verticalAlign: "top",
          hideTitle: true
        }
      },
      {
        tid: "labelModule.price",
        title: "售价",
        type: "text",
        options: {
          field: "price",
          testData: labelTestData("price", "¥1299.00"),
          height: 17,
          fontSize: 14,
          fontWeight: "700",
          color: "#d92d20",
          verticalAlign: "top",
          hideTitle: true
        }
      },
      {
        tid: "labelModule.avg",
        title: "均价",
        type: "text",
        options: {
          field: "avgPrice",
          testData: labelTestData("avg", "¥100"),
          height: 15,
          fontSize: 12,
          color: "#b45309",
          verticalAlign: "top",
          hideTitle: true
        }
      },
      {
        tid: "labelModule.condition",
        title: "SKU状态",
        type: "text",
        options: {
          field: "conditionType",
          testData: labelTestData("condition", "☑ 全新   □ 拆封   □ 裸盒   □ 瑕疵"),
          height: 12.5,
          fontSize: 9,
          verticalAlign: "top",
          hideTitle: true
        }
      }
    ]),
    new hiprint.PrintElementTypeGroup("条码 / 批次", [
      {
        tid: "labelModule.barcode",
        title: "条码",
        type: "text",
        options: {
          field: "barcode",
          testData: labelTestData("barcode", "6901234567890"),
          textType: "barcode",
          height: 40,
          verticalAlign: "top",
          hideTitle: true
        }
      },
      {
        tid: "labelModule.barcodeText",
        title: "条码数字",
        type: "text",
        options: {
          field: "barcode",
          testData: labelTestData("barcodeText", "6901234567890"),
          height: 12.5,
          fontSize: 10,
          textAlign: "center",
          verticalAlign: "top",
          hideTitle: true
        }
      },
      {
        tid: "labelModule.batch",
        title: "批次号",
        type: "text",
        options: {
          field: "batchNo",
          testData: labelTestData("batch", "批次 B0830-001"),
          height: 12.5,
          fontSize: 9,
          color: "#555555",
          verticalAlign: "top",
          hideTitle: true
        }
      },
      {
        tid: "labelModule.date",
        title: "入库日期",
        type: "text",
        options: {
          field: "purchasedAt",
          testData: labelTestData("date", "2026-08-30"),
          height: 12.5,
          fontSize: 9,
          color: "#555555",
          verticalAlign: "top",
          hideTitle: true
        }
      }
    ])
  ];

  function LabelProvider() {}
  LabelProvider.prototype.addElementTypes = function (context) {
    var hiprint = getHiprint();
    context.removePrintElementTypes('labelModule');
    context.addPrintElementTypes('labelModule', LABEL_ELEMENTS);
  };

  global.LabelProvider = LabelProvider;

  // 左侧可拖拽元素面板（分组），由 purchase_logs.php 读取渲染
  global.LABEL_PANEL_GROUPS = [
    {
      title: "商品信息",
      items: [
        { tid: "labelModule.name", title: "商品名称" },
        { tid: "labelModule.common", title: "常用名" },
        { tid: "labelModule.series", title: "系列" },
        { tid: "labelModule.price", title: "售价" },
        { tid: "labelModule.avg", title: "均价" },
        { tid: "labelModule.condition", title: "SKU状态" }
      ]
    },
    {
      title: "条码 / 批次",
      items: [
        { tid: "labelModule.barcode", title: "条码" },
        { tid: "labelModule.barcodeText", title: "条码数字" },
        { tid: "labelModule.batch", title: "批次号" },
        { tid: "labelModule.date", title: "入库日期" }
      ]
    }
  ];
})(typeof window !== 'undefined' ? window : this);
