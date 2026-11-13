<script setup>
import WorkorderTable from '@/components/workorder/WorkorderTable.vue'
</script>

<template>
  <WorkorderTable status="done" :show-inspector="true" :batch-print="true" />
</template>
