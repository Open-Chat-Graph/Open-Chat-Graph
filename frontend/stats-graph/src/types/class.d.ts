interface ChartFactory {
  render(data: ChartArgs, option: OpenChatChartOption, animation: boolean, limit: ChartLimit): void
  update(limit: ChartLimit): boolean
  setIsHour(isHour: boolean): void
  getIsHour(): boolean
}
