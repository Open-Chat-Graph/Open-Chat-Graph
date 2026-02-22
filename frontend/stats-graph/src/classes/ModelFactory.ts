export default class ModelFactory {
  static initChartArgs(): ChartArgs {
    return {
      date: [],
      graph1: [],
      graph2: [],
      time: [],
      totalCount: [],
    }
  }

  static initChartData(): ChartData {
    return {
      date: [],
      graph1: [],
      graph2: [],
      time: [],
      totalCount: [],
    }
  }

  static initOpenChatChartOption(): OpenChatChartOption {
    return {
      label1: '',
      label2: '',
      category: '',
      isRising: false,
    }
  }
}
