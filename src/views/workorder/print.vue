<script setup>
import WorkorderTable from '@/components/workorder/WorkorderTable.vue'
</script>

<template>
  <WorkorderTable status="print" :batch-print="true" />
</template>
