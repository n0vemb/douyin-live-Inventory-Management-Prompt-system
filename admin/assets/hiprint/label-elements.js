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

  var LABEL_ELEMENTS = [
    new hiprint.PrintElementTypeGroup("商品信息", [
      {
        tid: "labelModule.name",
        title: "商品名称",
        type: "text",
        options: {
          field: "productName",
          testData: "淘淘圈轮毂 19寸 5x114.3 ET35",
          height: 17,
          fontSize: 13,
          fontWeight: "700",
          hideTitle: true
        }
      },
      {
        tid: "labelModule.common",
        title: "常用名",
        type: "text",
        options: {
          field: "commonName",
          testData: "19寸改装款",
          height: 12.5,
          fontSize: 10,
          color: "#555555",
          hideTitle: true
        }
      },
      {
        tid: "labelModule.series",
        title: "系列",
        type: "text",
        options: {
          field: "series",
          testData: "GT 系列",
          height: 12.5,
          fontSize: 10,
          color: "#555555",
          hideTitle: true
        }
      },
      {
        tid: "labelModule.price",
        title: "售价",
        type: "text",
        options: {
          field: "price",
          testData: "¥1299.00",
          height: 17,
          fontSize: 14,
          fontWeight: "700",
          color: "#d92d20",
          hideTitle: true
        }
      },
      {
        tid: "labelModule.condition",
        title: "SKU状态",
        type: "text",
        options: {
          field: "conditionType",
          testData: "☑ 全新   □ 拆封   □ 裸盒   □ 瑕疵",
          height: 12.5,
          fontSize: 9,
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
          testData: "6901234567890",
          textType: "barcode",
          height: 40,
          hideTitle: true
        }
      },
      {
        tid: "labelModule.barcodeText",
        title: "条码数字",
        type: "text",
        options: {
          field: "barcode",
          testData: "6901234567890",
          height: 12.5,
          fontSize: 10,
          textAlign: "center",
          hideTitle: true
        }
      },
      {
        tid: "labelModule.batch",
        title: "批次号",
        type: "text",
        options: {
          field: "batchNo",
          testData: "批次 B0830-001",
          height: 12.5,
          fontSize: 9,
          color: "#555555",
          hideTitle: true
        }
      },
      {
        tid: "labelModule.date",
        title: "入库日期",
        type: "text",
        options: {
          field: "purchasedAt",
          testData: "2026-08-30",
          height: 12.5,
          fontSize: 9,
          color: "#555555",
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
