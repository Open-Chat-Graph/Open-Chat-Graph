import OpenChatChart from '../../OpenChatChart'

export default function getIncreaseLegendSpacingPlugin(ocChart: OpenChatChart) {
  const paddingTop = ocChart.isPC ? 4 : 4

  return {
    id: 'increase-legend-spacing',
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    beforeInit(chart: any) {
      // Get reference to the original fit function
      const originalFit = chart.legend.fit

      // Override the fit function
      chart.legend.fit = function fit() {
        // Call original function and bind scope in order to use `this` correctly inside it
        originalFit.bind(chart.legend)()
        // Change the height as suggested in another answers
        this.height += paddingTop
      }
    },
  }
}
